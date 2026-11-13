<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\RemboursementEcheance;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnexpectedValueException;

// use Carbon\Carbon;
class GestionCreditsService
{
    private const int DUREE_MAXIMALE_MOIS = 600;

    private const int MONTANT_MAXIMUM_CENTIMES = 999_999_999_999_999;

    public function listerCredits(User $utilisateur, array $filtres): LengthAwarePaginator
    {
        return $this->requeteCredits($utilisateur)
            ->when(
                isset($filtres['client_uuid']),
                fn (Builder $credits): Builder => $credits->whereHas(
                    'compte.client',
                    fn (Builder $clients): Builder => $clients->where(
                        'uuid',
                        $filtres['client_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['compte_uuid']),
                fn (Builder $credits): Builder => $credits->whereHas(
                    'compte',
                    fn (Builder $comptes): Builder => $comptes->where(
                        'uuid',
                        $filtres['compte_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['statut']),
                function (Builder $credits) use ($filtres): void {
                    if ($filtres['statut'] === 'en_retard') {
                        $credits->whereHas(
                            'echeances',
                            fn (Builder $echeances): Builder => $this->filtrerEcheancesEnRetard(
                                $echeances
                            )
                        );

                        return;
                    }

                    $credits->where('statut', $filtres['statut']);
                }
            )
            ->when(
                isset($filtres['date_debut']),
                fn (Builder $credits): Builder => $credits->whereDate(
                    'date_demande',
                    '>=',
                    $filtres['date_debut']
                )
            )
            ->when(
                isset($filtres['date_fin']),
                fn (Builder $credits): Builder => $credits->whereDate(
                    'date_demande',
                    '<=',
                    $filtres['date_fin']
                )
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $credits): Builder => $credits->whereHas(
                    'compte',
                    fn (Builder $comptes): Builder => $comptes->where(
                        'agence_id',
                        $filtres['agence_id']
                    )
                )
            )
            ->orderByDesc('date_demande')
            ->orderByDesc('id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function trouverCreditAccessibleParUuid(User $utilisateur, string $uuid): Credit
    {
        return $this->requeteCredits($utilisateur)
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function trouverEcheanceAccessibleParUuid(
        User $utilisateur,
        string $uuid
    ): EcheanceCredit {
        return EcheanceCredit::query()
            ->accessiblePar($utilisateur)
            ->with(['credit.compte.client'])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function listerEcheances(
        User $utilisateur,
        array $filtres
    ): LengthAwarePaginator {
        return EcheanceCredit::query()
            ->accessiblePar($utilisateur)
            ->with(['credit.compte.client'])
            ->when(
                isset($filtres['client_uuid']),
                fn (Builder $echeances): Builder => $echeances->whereHas(
                    'credit.compte.client',
                    fn (Builder $clients): Builder => $clients->where(
                        'uuid',
                        $filtres['client_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['credit_uuid']),
                fn (Builder $echeances): Builder => $echeances->whereHas(
                    'credit',
                    fn (Builder $credits): Builder => $credits->where(
                        'uuid',
                        $filtres['credit_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['statut_echeance_credit']),
                function (Builder $echeances) use ($filtres): void {
                    if ($filtres['statut_echeance_credit'] === 'en_retard') {
                        $this->filtrerEcheancesEnRetard($echeances);

                        return;
                    }

                    $echeances->where(
                        'statut_echeance_credit',
                        $filtres['statut_echeance_credit']
                    );
                }
            )
            ->when(
                filter_var($filtres['impayees'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn (Builder $echeances): Builder => $echeances
                    ->whereColumn('montant_paye', '<', 'montant_echeance')
                    ->where('statut_echeance_credit', '!=', 'annule')
            )
            ->when(
                filter_var($filtres['en_retard'] ?? false, FILTER_VALIDATE_BOOLEAN),
                fn (Builder $echeances): Builder => $this->filtrerEcheancesEnRetard(
                    $echeances
                )
            )
            ->when(
                isset($filtres['date_debut']),
                fn (Builder $echeances): Builder => $echeances->whereDate(
                    'date_echeance',
                    '>=',
                    $filtres['date_debut']
                )
            )
            ->when(
                isset($filtres['date_fin']),
                fn (Builder $echeances): Builder => $echeances->whereDate(
                    'date_echeance',
                    '<=',
                    $filtres['date_fin']
                )
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $echeances): Builder => $echeances->whereHas(
                    'credit.compte',
                    fn (Builder $comptes): Builder => $comptes->where(
                        'agence_id',
                        $filtres['agence_id']
                    )
                )
            )
            ->orderBy('date_echeance')
            ->orderBy('credit_id')
            ->orderBy('numero_echeance')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function creerDemande(
        User $utilisateur,
        Compte $compteCible,
        array $donnees
    ): Credit {
        return DB::transaction(function () use (
            $utilisateur,
            $compteCible,
            $donnees
        ): Credit {
            $compte = Compte::query()
                ->accessiblePar($utilisateur)
                ->whereKey($compteCible->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($compte->statut_compte !== 'actif') {
                throw new HttpException(422, 'Le compte associé doit être actif.');
            }

            $client = Client::query()
                ->accessiblePar($utilisateur)
                ->whereKey($compte->client_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $client->statut !== 'actif'
                || (int) $client->agence_id !== (int) $compte->agence_id
            ) {
                throw new HttpException(422, 'Le client n\'est pas éligible à une demande de crédit.');
            }

            if (
                Credit::withTrashed()
                    ->where('compte_id', $compte->id)
                    ->exists()
            ) {
                throw new HttpException(
                    409,
                    'Une demande de crédit existe déjà pour ce compte.'
                );
            }

            $credit = Credit::query()->create([
                'compte_id' => $compte->id,
                'conseiller_client_id' => $utilisateur->id,
                'montant_demande' => $this->formaterCentimes(
                    $this->montantEnCentimes($donnees['montant_demande'])
                ),
                'taux_interet_annuel' => $donnees['taux_interet_annuel'],
                'duree_mois' => $donnees['duree_mois'],
                'date_demande' => today(),
                'statut' => 'en_etude',
                'observations' => $donnees['observations'] ?? null,
            ]);

            return $this->chargerCredit($credit, $utilisateur);
        }, 3);
    }

    public function modifierDemande(
        User $utilisateur,
        string $uuid,
        array $donnees
    ): Credit {
        return DB::transaction(function () use ($utilisateur, $uuid, $donnees): Credit {
            $credit = $this->verrouillerCredit($utilisateur, $uuid);
            Gate::forUser($utilisateur)->authorize('modifierDemande', $credit);

            foreach (['montant_demande', 'taux_interet_annuel', 'duree_mois', 'observations'] as $champ) {
                if (array_key_exists($champ, $donnees)) {
                    $credit->{$champ} = $champ === 'montant_demande'
                        ? $this->formaterCentimes(
                            $this->montantEnCentimes($donnees[$champ])
                        )
                        : $donnees[$champ];
                }
            }

            $credit->save();

            return $this->chargerCredit($credit, $utilisateur);
        }, 3);
    }

    public function soumettreDemande(User $utilisateur, string $uuid): Credit
    {
        return DB::transaction(function () use ($utilisateur, $uuid): Credit {
            $credit = $this->verrouillerCredit($utilisateur, $uuid);
            Gate::forUser($utilisateur)->authorize('soumettreDemande', $credit);
            $credit->forceFill(['statut' => 'en_attente'])->save();

            return $this->chargerCredit($credit, $utilisateur);
        }, 3);
    }

    public function approuverDemande(
        User $validateur,
        string $uuid,
        ?string $montantAccorde
    ): Credit {
        return DB::transaction(function () use (
            $validateur,
            $uuid,
            $montantAccorde
        ): Credit {
            $credit = $this->verrouillerCredit($validateur, $uuid);
            Gate::forUser($validateur)->authorize('approuver', $credit);

            $compte = Compte::query()
                ->accessiblePar($validateur)
                ->whereKey($credit->compte_id)
                ->lockForUpdate()
                ->firstOrFail();
            $client = Client::query()
                ->accessiblePar($validateur)
                ->whereKey($compte->client_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $compte->statut_compte !== 'actif'
                || $client->statut !== 'actif'
                || (int) $compte->agence_id !== (int) $client->agence_id
            ) {
                throw new HttpException(
                    422,
                    'Le compte ou le client n\'est plus éligible au déboursement.'
                );
            }

            $montantAccordeCentimes = $this->montantEnCentimes(
                $montantAccorde ?? (string) $credit->montant_demande
            );
            $montantDemandeCentimes = $this->montantEnCentimes(
                (string) $credit->montant_demande
            );

            if (
                $montantAccordeCentimes <= 0
                || $montantAccordeCentimes > $montantDemandeCentimes
            ) {
                throw new HttpException(
                    422,
                    'Le montant accordé doit être positif et ne peut pas dépasser le montant demandé.'
                );
            }

            $cleDeboursement = 'credit-debourse-'.$credit->uuid;
            if (Transaction::withTrashed()->where('cle_idempotence', $cleDeboursement)->exists()) {
                throw new HttpException(
                    409,
                    'Ce crédit possède déjà une transaction de déboursement.'
                );
            }

            $echeancier = $this->calculerEcheancier(
                $montantAccordeCentimes,
                (float) $credit->taux_interet_annuel,
                (int) $credit->duree_mois
            );
            $dateDebutRemboursement = Carbon::today()->addMonthNoOverflow();

            $transaction = $this->creerTransactionCredit(
                $validateur,
                $compte,
                'deboursement',
                $montantAccordeCentimes,
                null,
                $compte,
                $cleDeboursement,
                hash('sha256', json_encode([
                    'type' => 'deboursement',
                    'credit_uuid' => $credit->uuid,
                    'compte_id' => $compte->id,
                    'montant_centimes' => $montantAccordeCentimes,
                ], JSON_THROW_ON_ERROR)),
                'Déboursement du crédit '.$credit->uuid
            );

            $compte->forceFill([
                'solde' => $this->formaterCentimes(
                    $this->additionnerCentimes(
                        $this->montantEnCentimes((string) $compte->solde),
                        $montantAccordeCentimes
                    )
                ),
            ])->save();

            foreach ($echeancier['echeances'] as $echeance) {
                EcheanceCredit::query()->create([
                    'credit_id' => $credit->id,
                    ...$echeance,
                ]);
            }

            $anciennesValeurs = [
                'statut' => $credit->statut,
                'montant_accorde' => $credit->montant_accorde,
            ];
            $credit->forceFill([
                'montant_accorde' => $this->formaterCentimes($montantAccordeCentimes),
                'mensualite' => $this->formaterCentimes(
                    $echeancier['mensualite_centimes']
                ),
                'capital_restant_du' => $this->formaterCentimes($montantAccordeCentimes),
                'montant_total_interets' => $this->formaterCentimes(
                    $echeancier['total_interets_centimes']
                ),
                'date_approbation' => today(),
                'date_deboursement' => today(),
                'date_debut_remboursement' => $dateDebutRemboursement,
                'date_fin_prevue' => $dateDebutRemboursement
                    ->copy()
                    ->addMonthsNoOverflow((int) $credit->duree_mois - 1),
                'valide_par' => $validateur->id,
                'statut' => 'debourse',
            ])->save();

            $this->journaliserDecision(
                $validateur,
                $credit,
                'credit.approuve',
                $anciennesValeurs,
                [
                    'statut' => 'debourse',
                    'montant_accorde' => $credit->montant_accorde,
                    'transaction_uuid' => $transaction->uuid,
                ]
            );

            return $this->chargerCredit($credit, $validateur);
        }, 3);
    }

    public function rejeterDemande(
        User $validateur,
        string $uuid,
        string $motif
    ): Credit {
        return DB::transaction(function () use ($validateur, $uuid, $motif): Credit {
            $credit = $this->verrouillerCredit($validateur, $uuid);
            Gate::forUser($validateur)->authorize('rejeter', $credit);
            $ancienStatut = $credit->statut;
            $credit->forceFill([
                'statut' => 'rejete',
                'motif_rejet' => trim($motif),
                'valide_par' => $validateur->id,
                'date_approbation' => today(),
            ])->save();

            $this->journaliserDecision(
                $validateur,
                $credit,
                'credit.rejete',
                ['statut' => $ancienStatut],
                [
                    'statut' => 'rejete',
                    'motif_rejet' => $credit->motif_rejet,
                ]
            );

            return $this->chargerCredit($credit, $validateur);
        }, 3);
    }

    /**
     * @return array{
     *     transaction: Transaction,
     *     echeances: array<int, array{uuid: string, montant_affecte: string}>,
     *     rejoue: bool
     * }
     */
    public function enregistrerRemboursement(
        User $caissier,
        string $uuidCredit,
        string $uuidCompte,
        string $montant,
        ?string $motif,
        string $cleIdempotence
    ): array {
        $montantCentimes = $this->montantEnCentimes($montant);
        $empreinte = $this->empreinteRemboursement(
            $caissier,
            $uuidCredit,
            $uuidCompte,
            $montantCentimes,
            $motif
        );

        try {
            return DB::transaction(function () use (
                $caissier,
                $uuidCredit,
                $uuidCompte,
                $montantCentimes,
                $motif,
                $cleIdempotence,
                $empreinte
            ): array {
                $credit = $this->verrouillerCredit($caissier, $uuidCredit);
                Gate::forUser($caissier)->authorize('enregistrerRemboursement', $credit);

                if ($resultat = $this->rejouerRemboursement($cleIdempotence, $empreinte)) {
                    return $resultat;
                }

                if (
                    ! in_array(
                        $credit->statut,
                        ['debourse', 'en_remboursement', 'en_retard'],
                        true
                    )
                    || $credit->date_deboursement === null
                ) {
                    throw new HttpException(
                        422,
                        'Le crédit doit être déboursé et non soldé pour recevoir un remboursement.'
                    );
                }

                $compteCredit = Compte::query()
                    ->accessiblePar($caissier)
                    ->whereKey($credit->compte_id)
                    ->firstOrFail();
                $compte = Compte::query()
                    ->accessiblePar($caissier)
                    ->where('uuid', $uuidCompte)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    (int) $compte->client_id !== (int) $compteCredit->client_id
                    || (int) $compte->agence_id !== (int) $compteCredit->agence_id
                ) {
                    throw new HttpException(404, 'Compte introuvable.');
                }

                if ($compte->statut_compte !== 'actif') {
                    throw new HttpException(422, 'Le compte de paiement doit être actif.');
                }

                $client = Client::query()
                    ->accessiblePar($caissier)
                    ->whereKey($compte->client_id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($client->statut !== 'actif') {
                    throw new HttpException(422, 'Le client doit être actif.');
                }

                $echeances = EcheanceCredit::query()
                    ->where('credit_id', $credit->id)
                    ->whereNotIn('statut_echeance_credit', ['paye', 'annule'])
                    ->orderBy('numero_echeance')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $montantRestantDu = $echeances->sum(
                    fn (EcheanceCredit $echeance): int => max(
                        0,
                        $this->montantEnCentimes((string) $echeance->montant_echeance)
                            - $this->montantEnCentimes((string) $echeance->montant_paye)
                    )
                );

                if ($montantCentimes <= 0 || $montantCentimes > $montantRestantDu) {
                    throw new HttpException(
                        422,
                        'Le remboursement doit être positif et ne peut pas dépasser le montant restant dû.'
                    );
                }

                $soldeCompte = $this->montantEnCentimes((string) $compte->solde);
                if ($soldeCompte < $montantCentimes) {
                    throw new HttpException(422, 'Le solde disponible est insuffisant.');
                }

                $transaction = $this->creerTransactionCredit(
                    $caissier,
                    $compte,
                    'remboursement',
                    $montantCentimes,
                    $compte,
                    null,
                    $cleIdempotence,
                    $empreinte,
                    $motif
                );

                $compte->forceFill([
                    'solde' => $this->formaterCentimes(
                        $soldeCompte - $montantCentimes
                    ),
                ])->save();

                $montantAffecteRestant = $montantCentimes;
                $capitalRembourse = 0;
                $echeancesRemboursees = [];

                foreach ($echeances as $echeance) {
                    if ($montantAffecteRestant === 0) {
                        break;
                    }

                    $montantEcheance = $this->montantEnCentimes(
                        (string) $echeance->montant_echeance
                    );
                    $montantDejaPaye = $this->montantEnCentimes(
                        (string) $echeance->montant_paye
                    );
                    $affectation = min(
                        $montantAffecteRestant,
                        $montantEcheance - $montantDejaPaye
                    );
                    $interetEcheance = $this->montantEnCentimes(
                        (string) $echeance->interet
                    );
                    $interetDejaPaye = min($interetEcheance, $montantDejaPaye);
                    $interetAffecte = min(
                        $affectation,
                        $interetEcheance - $interetDejaPaye
                    );
                    $capitalAffecte = $affectation - $interetAffecte;
                    $nouveauMontantPaye = $montantDejaPaye + $affectation;
                    $echeanceSoldee = $nouveauMontantPaye === $montantEcheance;

                    RemboursementEcheance::query()->create([
                        'transaction_id' => $transaction->id,
                        'echeance_credit_id' => $echeance->id,
                        'montant' => $this->formaterCentimes($affectation),
                        'capital' => $this->formaterCentimes($capitalAffecte),
                        'interet' => $this->formaterCentimes($interetAffecte),
                    ]);

                    $echeance->forceFill([
                        'montant_paye' => $this->formaterCentimes($nouveauMontantPaye),
                        'date_paiement' => $echeanceSoldee ? today() : $echeance->date_paiement,
                        'statut_echeance_credit' => $echeanceSoldee
                            ? 'paye'
                            : ($echeance->date_echeance->isBefore(today())
                                ? 'en_retard'
                                : 'partiellement_paye'),
                    ])->save();

                    $montantAffecteRestant -= $affectation;
                    $capitalRembourse += $capitalAffecte;
                    $echeancesRemboursees[] = [
                        'uuid' => $echeance->uuid,
                        'montant_affecte' => $this->formaterCentimes($affectation),
                    ];
                }

                if ($montantAffecteRestant !== 0) {
                    throw new UnexpectedValueException(
                        'La répartition du remboursement est incomplète.'
                    );
                }

                $echeancesRestantes = EcheanceCredit::query()
                    ->where('credit_id', $credit->id)
                    ->whereNotIn('statut_echeance_credit', ['paye', 'annule'])
                    ->get();
                $creditSolde = $echeancesRestantes->isEmpty();
                $creditEnRetard = $echeancesRestantes->contains(
                    fn (EcheanceCredit $echeance): bool => $echeance
                        ->date_echeance
                        ->isBefore(today())
                );
                $capitalRestant = max(
                    0,
                    $this->montantEnCentimes(
                        (string) $credit->capital_restant_du
                    ) - $capitalRembourse
                );

                $credit->forceFill([
                    'capital_restant_du' => $this->formaterCentimes($capitalRestant),
                    'statut' => $creditSolde
                        ? 'solde'
                        : ($creditEnRetard ? 'en_retard' : 'en_remboursement'),
                ])->save();

                $transaction->load('remboursementsEcheances.echeance');

                return [
                    'transaction' => $transaction,
                    'echeances' => $echeancesRemboursees,
                    'rejoue' => false,
                ];
            }, 3);
        } catch (QueryException $exception) {
            if (! $this->estErreurCleIdempotence($exception)) {
                throw $exception;
            }

            $resultat = $this->rejouerRemboursement($cleIdempotence, $empreinte);
            if ($resultat === null) {
                throw $exception;
            }

            return $resultat;
        }
    }

    public function suivi(User $utilisateur): array
    {
        $credits = Credit::query()->accessiblePar($utilisateur);
        $echeances = EcheanceCredit::query()->accessiblePar($utilisateur);

        return [
            'credits_en_instruction' => (clone $credits)
                ->where('statut', 'en_etude')
                ->count(),
            'credits_en_attente_validation' => (clone $credits)
                ->where('statut', 'en_attente')
                ->count(),
            'credits_approuves' => (clone $credits)
                ->whereNotNull('date_approbation')
                ->whereNull('motif_rejet')
                ->count(),
            'credits_debourses' => (clone $credits)
                ->whereNotNull('date_deboursement')
                ->count(),
            'credits_soldes' => (clone $credits)
                ->where('statut', 'solde')
                ->count(),
            'credits_en_retard' => (clone $credits)
                ->whereHas(
                    'echeances',
                    fn (Builder $requete): Builder => $this->filtrerEcheancesEnRetard(
                        $requete
                    )
                )
                ->count(),
            'credits_impayes_plus_de_30_jours' => (clone $credits)
                ->whereHas(
                    'echeances',
                    fn (Builder $requete): Builder => $this->filtrerEcheancesEnRetard(
                        $requete
                    )->whereDate('date_echeance', '<', today()->subDays(30))
                )
                ->count(),
            'montant_total_credits_debourses' => $this->formaterCentimes(
                $this->montantEnCentimes(
                    (string) ((clone $credits)
                        ->whereNotNull('date_deboursement')
                        ->sum('montant_accorde') ?: '0')
                )
            ),
            'capital_restant_du' => $this->formaterCentimes(
                $this->montantEnCentimes(
                    (string) ((clone $credits)
                        ->whereNotNull('date_deboursement')
                        ->sum('capital_restant_du') ?: '0')
                )
            ),
            'montant_echeances_impayees' => $this->formaterCentimes(
                $this->montantEnCentimes(
                    (string) ($echeances
                        ->whereNotIn('statut_echeance_credit', ['paye', 'annule'])
                        ->selectRaw(
                            'COALESCE(SUM(montant_echeance - montant_paye), 0) as total'
                        )
                        ->value('total') ?: '0')
                )
            ),
        ];
    }

    private function requeteCredits(User $utilisateur): Builder
    {
        return Credit::query()
            ->accessiblePar($utilisateur)
            ->with([
                'compte:id,uuid,agence_id,client_id,numero_compte,type_compte,devise',
                'compte.client:id,uuid,code_client,nom_client,prenom_client,statut',
                'conseillerClient:id,uuid,nom,prenom',
                'validateur:id,uuid,nom,prenom',
            ])
            ->withCount('echeances')
            ->withExists([
                'echeances as a_des_echeances_en_retard' => fn (Builder $echeances): Builder => $this
                    ->filtrerEcheancesEnRetard($echeances),
            ]);
    }

    private function chargerCredit(Credit $credit, User $utilisateur): Credit
    {
        return $this->requeteCredits($utilisateur)
            ->whereKey($credit->id)
            ->firstOrFail();
    }

    private function verrouillerCredit(User $utilisateur, string $uuid): Credit
    {
        return Credit::query()
            ->accessiblePar($utilisateur)
            ->where('uuid', $uuid)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function filtrerEcheancesEnRetard(Builder $requete): Builder
    {
        return $requete
            ->whereDate('date_echeance', '<', today())
            ->whereColumn('montant_paye', '<', 'montant_echeance')
            ->where('statut_echeance_credit', '!=', 'annule');
    }

    /**
     * @return array{
     *     mensualite_centimes: int,
     *     total_interets_centimes: int,
     *     echeances: list<array{
     *         numero_echeance: int,
     *         date_echeance: string,
     *         capital: string,
     *         interet: string,
     *         montant_echeance: string,
     *         montant_paye: string,
     *         statut_echeance_credit: string
     *     }>
     * }
     */
    private function calculerEcheancier(
        int $capitalInitial,
        float $tauxAnnuel,
        int $dureeMois
    ): array {
        if ($dureeMois < 1 || $dureeMois > self::DUREE_MAXIMALE_MOIS) {
            throw new HttpException(
                422,
                'La durée du crédit doit être comprise entre 1 et 600 mois.'
            );
        }

        $tauxMensuel = $tauxAnnuel / 1200;
        $mensualite = $tauxMensuel === 0.0
            ? (int) round($capitalInitial / $dureeMois, 0, PHP_ROUND_HALF_UP)
            : (int) round(
                $capitalInitial
                    * $tauxMensuel
                    / (1 - (1 + $tauxMensuel) ** (-$dureeMois)),
                0,
                PHP_ROUND_HALF_UP
            );

        if ($mensualite <= 0 || $mensualite > self::MONTANT_MAXIMUM_CENTIMES) {
            throw new HttpException(422, 'Les conditions du crédit ne permettent pas de générer un échéancier valide.');
        }

        $capitalRestant = $capitalInitial;
        $totalInterets = 0;
        $echeances = [];
        $prochaineDate = Carbon::today()->addMonthNoOverflow();

        for ($numero = 1; $numero <= $dureeMois; $numero++) {
            $interet = (int) round(
                $capitalRestant * $tauxMensuel,
                0,
                PHP_ROUND_HALF_UP
            );
            $capital = $numero === $dureeMois
                ? $capitalRestant
                : min($capitalRestant, $mensualite - $interet);

            if ($capital <= 0) {
                throw new HttpException(
                    422,
                    'Les conditions du crédit empêchent le remboursement du capital pendant la durée demandée.'
                );
            }

            $montantEcheance = $capital + $interet;
            if ($montantEcheance > self::MONTANT_MAXIMUM_CENTIMES) {
                throw new HttpException(422, 'Une échéance dépasse la capacité de stockage.');
            }

            $echeances[] = [
                'numero_echeance' => $numero,
                'date_echeance' => $prochaineDate
                    ->copy()
                    ->addMonthsNoOverflow($numero - 1)
                    ->toDateString(),
                'capital' => $this->formaterCentimes($capital),
                'interet' => $this->formaterCentimes($interet),
                'montant_echeance' => $this->formaterCentimes($montantEcheance),
                'montant_paye' => '0.00',
                'statut_echeance_credit' => 'a_payer',
            ];

            $capitalRestant -= $capital;
            $totalInterets = $this->additionnerCentimes($totalInterets, $interet);
        }

        if ($capitalRestant !== 0) {
            throw new UnexpectedValueException('L\'échéancier ne rembourse pas le capital total.');
        }

        return [
            'mensualite_centimes' => $mensualite,
            'total_interets_centimes' => $totalInterets,
            'echeances' => $echeances,
        ];
    }

    private function creerTransactionCredit(
        User $utilisateur,
        Compte $compteReference,
        string $type,
        int $montantCentimes,
        ?Compte $compteSource,
        ?Compte $compteDestinataire,
        string $cleIdempotence,
        string $empreinte,
        ?string $motif
    ): Transaction {
        return Transaction::query()->create([
            'reference_transaction' => $this->genererReferenceTransaction(),
            'agence_id' => $compteReference->agence_id,
            'client_id' => $compteReference->client_id,
            'user_id' => $utilisateur->id,
            'type_transaction' => $type,
            'montant' => $this->formaterCentimes($montantCentimes),
            'devise' => $compteReference->devise,
            'date_transaction' => now(),
            'compte_source_id' => $compteSource?->id,
            'compte_destinataire_id' => $compteDestinataire?->id,
            'statut_transaction' => 'terminee',
            'valide_par' => $utilisateur->id,
            'date_validation' => now(),
            'cle_idempotence' => $cleIdempotence,
            'motif' => $motif,
            'metadata' => ['idempotence' => ['empreinte' => $empreinte]],
        ]);
    }

    private function genererReferenceTransaction(): string
    {
        do {
            $reference = 'TRX-'.Str::upper(Str::random(26));
        } while (Transaction::withTrashed()
            ->where('reference_transaction', $reference)
            ->exists());

        return $reference;
    }

    private function journaliserDecision(
        User $validateur,
        Credit $credit,
        string $action,
        array $anciennesValeurs,
        array $nouvellesValeurs
    ): void {
        AuditLog::query()->create([
            'user_id' => $validateur->id,
            'action' => $action,
            'table_concernee' => 'credits',
            'enregistrement_id' => $credit->id,
            'anciennes_valeurs' => $anciennesValeurs,
            'nouvelles_valeurs' => $nouvellesValeurs,
            'description' => $action === 'credit.approuve'
                ? 'Approbation et déboursement atomique du crédit '.$credit->uuid
                : 'Rejet de la demande de crédit '.$credit->uuid,
        ]);
    }

    /**
     * @return array{
     *     transaction: Transaction,
     *     echeances: array<int, array{uuid: string, montant_affecte: string}>,
     *     rejoue: bool
     * }|null
     */
    private function rejouerRemboursement(
        string $cleIdempotence,
        string $empreinte
    ): ?array {
        $transaction = Transaction::withTrashed()
            ->where('cle_idempotence', $cleIdempotence)
            ->first();

        if ($transaction === null) {
            return null;
        }

        if (
            $transaction->trashed()
            || $transaction->type_transaction !== 'remboursement'
            || ! hash_equals(
                (string) data_get($transaction->metadata, 'idempotence.empreinte'),
                $empreinte
            )
        ) {
            throw new HttpException(
                409,
                'Cette clé d\'idempotence a déjà été utilisée avec une autre opération.'
            );
        }

        $transaction->load('remboursementsEcheances.echeance');

        return [
            'transaction' => $transaction,
            'echeances' => $transaction->remboursementsEcheances
                ->map(fn (RemboursementEcheance $remboursement): array => [
                    'uuid' => $remboursement->echeance->uuid,
                    'montant_affecte' => $remboursement->montant,
                ])
                ->all(),
            'rejoue' => true,
        ];
    }

    private function empreinteRemboursement(
        User $caissier,
        string $uuidCredit,
        string $uuidCompte,
        int $montantCentimes,
        ?string $motif
    ): string {
        return hash('sha256', json_encode([
            'type' => 'remboursement',
            'utilisateur_id' => $caissier->id,
            'agence_id' => $caissier->agence_id,
            'credit_uuid' => $uuidCredit,
            'compte_uuid' => $uuidCompte,
            'montant_centimes' => $montantCentimes,
            'motif' => $motif,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function estErreurCleIdempotence(QueryException $exception): bool
    {
        if (! in_array((string) $exception->getCode(), ['23000', '23505'], true)) {
            return false;
        }

        return str_contains(strtolower($exception->getMessage()), 'cle_idempotence')
            || str_contains(strtolower($exception->getMessage()), 'transactions_cle_idempotence_unique');
    }

    private function montantEnCentimes(string|int|float $montant): int
    {
        $valeur = (string) $montant;

        if (is_float($montant) || str_contains(strtolower($valeur), 'e')) {
            $valeur = number_format((float) $montant, 2, '.', '');
        }

        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $valeur)) {
            throw new UnexpectedValueException('Un montant comptable est invalide.');
        }

        [$unites, $decimales] = array_pad(explode('.', $valeur, 2), 2, '0');
        $centimes = ((int) $unites * 100) + (int) str_pad($decimales, 2, '0');

        if ($centimes > self::MONTANT_MAXIMUM_CENTIMES) {
            throw new UnexpectedValueException('Un montant dépasse la capacité du schéma.');
        }

        return $centimes;
    }

    private function additionnerCentimes(int $montant, int $variation): int
    {
        if (
            ($variation > 0 && $montant > PHP_INT_MAX - $variation)
            || ($variation < 0 && $montant < PHP_INT_MIN - $variation)
        ) {
            throw new HttpException(422, 'Le montant dépasse la capacité de calcul.');
        }

        return $montant + $variation;
    }

    private function formaterCentimes(int $montant): string
    {
        $signe = $montant < 0 ? '-' : '';
        $absolu = abs($montant);

        return sprintf(
            '%s%d.%02d',
            $signe,
            intdiv($absolu, 100),
            $absolu % 100
        );
    }
}
