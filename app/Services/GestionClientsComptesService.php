<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Compte;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use OverflowException;

class GestionClientsComptesService
{
    /**
     * @param array{
     *     recherche?: string,
     *     statut?: string,
     *     agence_id?: int,
     *     per_page?: int
     * } $filtres
     */
    public function listerClients(User $utilisateur, array $filtres): LengthAwarePaginator
    {
        return Client::query()
            ->accessiblePar($utilisateur)
            ->with([
                'agence:id,uuid,code_agence,nom_agence',
                'comptes' => fn (HasMany $comptes): HasMany => $comptes
                    ->whereHas(
                        'client',
                        fn (Builder $client): Builder => $client->whereColumn(
                            'clients.agence_id',
                            'comptes.agence_id'
                        )
                    ),
            ])
            ->withCount([
                'comptes' => fn (Builder $comptes): Builder => $comptes
                    ->whereColumn('comptes.agence_id', 'clients.agence_id'),
            ])
            ->when(
                isset($filtres['recherche']) && $filtres['recherche'] !== '',
                function (Builder $requete) use ($filtres): void {
                    $terme = '%'.$filtres['recherche'].'%';
                    $requete->where(function (Builder $recherche) use ($terme): void {
                        $recherche
                            ->where('nom_client', 'like', $terme)
                            ->orWhere('prenom_client', 'like', $terme)
                            ->orWhere('telephone_client', 'like', $terme)
                            ->orWhere('email_client', 'like', $terme)
                            ->orWhere('code_client', 'like', $terme);
                    });
                }
            )
            ->when(
                isset($filtres['statut']),
                fn (Builder $requete): Builder => $requete->where(
                    'statut',
                    $filtres['statut']
                )
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $requete): Builder => $requete->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->orderBy('nom_client')
            ->orderBy('prenom_client')
            ->orderBy('id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function trouverClientAccessibleParUuid(User $utilisateur, string $uuid): Client
    {
        return Client::query()
            ->accessiblePar($utilisateur)
            ->with([
                'agence:id,uuid,code_agence,nom_agence',
                'comptes' => fn (HasMany $comptes): HasMany => $comptes
                    ->whereHas(
                        'client',
                        fn (Builder $client): Builder => $client->whereColumn(
                            'clients.agence_id',
                            'comptes.agence_id'
                        )
                    )
                    ->with('agence:id,uuid,code_agence,nom_agence'),
            ])
            ->withCount([
                'comptes' => fn (Builder $comptes): Builder => $comptes
                    ->whereColumn('comptes.agence_id', 'clients.agence_id'),
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function creerClient(User $utilisateur, array $donnees): Client
    {
        $utilisateur->loadMissing('agence:id,code_agence');

        $client = Client::query()->create([
            ...$donnees,
            'agence_id' => $utilisateur->agence_id,
            'code_client' => $this->genererCodeClient($utilisateur),
            'statut' => 'actif',
            'cree_par_user_id' => $utilisateur->id,
        ]);

        return $this->chargerRelationsClient($client);
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function modifierClient(Client $client, array $donnees): Client
    {
        $client->fill($donnees)->save();

        return $this->chargerRelationsClient($client);
    }

    /**
     * @param array{
     *     numero_compte?: string,
     *     client_uuid?: string,
     *     type_compte?: string,
     *     statut_compte?: string,
     *     agence_id?: int,
     *     per_page?: int
     * } $filtres
     */
    public function listerComptes(User $utilisateur, array $filtres): LengthAwarePaginator
    {
        return Compte::query()
            ->accessiblePar($utilisateur)
            ->whereHas(
                'client',
                fn (Builder $client): Builder => $client->whereColumn(
                    'clients.agence_id',
                    'comptes.agence_id'
                )
            )
            ->with([
                'client:id,uuid,code_client,nom_client,prenom_client',
                'agence:id,uuid,code_agence,nom_agence',
            ])
            ->when(
                isset($filtres['numero_compte']),
                fn (Builder $requete): Builder => $requete->where(
                    'numero_compte',
                    'like',
                    '%'.$filtres['numero_compte'].'%'
                )
            )
            ->when(
                isset($filtres['client_uuid']),
                fn (Builder $requete): Builder => $requete->whereHas(
                    'client',
                    fn (Builder $client): Builder => $client->where(
                        'uuid',
                        $filtres['client_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['type_compte']),
                fn (Builder $requete): Builder => $requete->where(
                    'type_compte',
                    $filtres['type_compte']
                )
            )
            ->when(
                isset($filtres['statut_compte']),
                fn (Builder $requete): Builder => $requete->where(
                    'statut_compte',
                    $filtres['statut_compte']
                )
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $requete): Builder => $requete->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->orderBy('numero_compte')
            ->orderBy('id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function trouverCompteAccessibleParUuid(User $utilisateur, string $uuid): Compte
    {
        return Compte::query()
            ->accessiblePar($utilisateur)
            ->whereHas(
                'client',
                fn (Builder $client): Builder => $client->whereColumn(
                    'clients.agence_id',
                    'comptes.agence_id'
                )
            )
            ->with([
                'client:id,uuid,code_client,nom_client,prenom_client',
                'agence:id,uuid,code_agence,nom_agence',
            ])
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array{type_compte: string}  $donnees
     */
    public function ouvrirCompte(
        User $utilisateur,
        Client $client,
        array $donnees
    ): Compte {
        $client->loadMissing('agence:id,code_agence');
        $numeroCompte = $this->genererNumeroCompte($client->agence->code_agence);

        $compte = Compte::query()->create([
            'agence_id' => $client->agence_id,
            'client_id' => $client->id,
            'numero_compte' => $numeroCompte,
            'type_compte' => $donnees['type_compte'],
            'solde' => '0.00',
            'solde_initial' => '0.00',
            'devise' => 'XOF',
            'date_ouverture' => today(),
            'statut_compte' => 'actif',
            'cree_par_user_id' => $utilisateur->id,
        ]);

        return $compte->load([
            'client:id,uuid,code_client,nom_client,prenom_client',
            'agence:id,uuid,code_agence,nom_agence',
        ]);
    }

    /**
     * @return array{
     *     compte: array<string, mixed>,
     *     periode: array<string, string>,
     *     totaux: array<string, string>,
     *     operations: LengthAwarePaginator
     * }
     */
    public function genererReleve(
        Compte $compte,
        string $dateDebut,
        string $dateFin,
        int $operationsParPage = 15
    ): array {
        $lendemainFin = Carbon::parse($dateFin)->addDay()->toDateString();
        $mouvementsAvantPeriode = $this->agregerMouvements(
            $compte,
            limiteSuperieure: $dateDebut.' 00:00:00'
        );
        $mouvementsPeriode = $this->agregerMouvements(
            $compte,
            limiteInferieure: $dateDebut.' 00:00:00',
            limiteSuperieure: $lendemainFin.' 00:00:00'
        );

        $soldeInitial = $this->ajouterCentimes(
            $this->montantEnCentimes((string) $compte->solde_initial),
            $this->ajouterCentimes(
                $mouvementsAvantPeriode['credits'],
                -$mouvementsAvantPeriode['debits']
            )
        );
        $soldeFinal = $this->ajouterCentimes(
            $soldeInitial,
            $this->ajouterCentimes(
                $mouvementsPeriode['credits'],
                -$mouvementsPeriode['debits']
            )
        );

        $operations = $this->requeteTransactionsComptabilisees($compte)
            ->where(function (Builder $requete) use ($dateDebut, $lendemainFin): void {
                $requete
                    ->where('date_transaction', '>=', $dateDebut.' 00:00:00')
                    ->where('date_transaction', '<', $lendemainFin.' 00:00:00');
            })
            ->orderBy('date_transaction')
            ->orderBy('id')
            ->paginate($operationsParPage);

        $soldeCourant = $soldeInitial;

        if ($operations->isNotEmpty()) {
            $mouvementsAvantPremiereOperation = $this->agregerMouvements(
                $compte,
                limiteTransaction: $operations->first()
            );
            $soldeCourant = $this->ajouterCentimes(
                $this->montantEnCentimes((string) $compte->solde_initial),
                $this->ajouterCentimes(
                    $mouvementsAvantPremiereOperation['credits'],
                    -$mouvementsAvantPremiereOperation['debits']
                )
            );
        }

        $operations->setCollection(
            $operations->getCollection()->map(
                function (Transaction $transaction) use (
                    $compte,
                    &$soldeCourant
                ): array {
                    $estDebit = $transaction->compte_source_id === $compte->id;
                    $estCredit = $transaction->compte_destinataire_id === $compte->id;
                    $montant = $this->montantEnCentimes((string) $transaction->montant);

                    if ($estDebit) {
                        $soldeCourant = $this->ajouterCentimes($soldeCourant, -$montant);
                    }

                    if ($estCredit) {
                        $soldeCourant = $this->ajouterCentimes($soldeCourant, $montant);
                    }

                    return [
                        'date_heure' => $transaction->date_transaction->toIso8601String(),
                        'reference_transaction' => $transaction->reference_transaction,
                        'type_operation' => $transaction->type_transaction,
                        'libelle' => $transaction->motif
                            ?: $this->libelleTransaction($transaction->type_transaction),
                        'montant' => $transaction->montant,
                        'sens' => $estDebit ? 'debit' : 'credit',
                        'solde_apres_operation' => $this->formaterCentimes($soldeCourant),
                    ];
                }
            )
        );

        return [
            'compte' => [
                'uuid' => $compte->uuid,
                'numero_compte' => $compte->numero_compte,
                'type_compte' => $compte->type_compte,
                'statut_compte' => $compte->statut_compte,
                'devise' => $compte->devise,
                'client' => [
                    'uuid' => $compte->client->uuid,
                    'code_client' => $compte->client->code_client,
                    'nom_client' => $compte->client->nom_client,
                    'prenom_client' => $compte->client->prenom_client,
                ],
            ],
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'solde_initial' => $this->formaterCentimes($soldeInitial),
            ],
            'totaux' => [
                'total_credits' => $this->formaterCentimes($mouvementsPeriode['credits']),
                'total_debits' => $this->formaterCentimes($mouvementsPeriode['debits']),
                'solde_final' => $this->formaterCentimes($soldeFinal),
            ],
            'operations' => $operations,
        ];
    }

    private function genererCodeClient(User $utilisateur): string
    {
        do {
            $code = 'CLI-'.$utilisateur->agence->code_agence.'-'.Str::upper(Str::random(14));
        } while (Client::query()->where('code_client', $code)->exists());

        return $code;
    }

    private function genererNumeroCompte(string $codeAgence): string
    {
        do {
            $numero = 'C-'.$codeAgence.'-'.Str::upper(Str::random(16));
        } while (Compte::query()->where('numero_compte', $numero)->exists());

        return $numero;
    }

    private function chargerRelationsClient(Client $client): Client
    {
        return $client
            ->load([
                'agence:id,uuid,code_agence,nom_agence',
                'comptes' => fn (HasMany $comptes): HasMany => $comptes
                    ->whereHas(
                        'client',
                        fn (Builder $client): Builder => $client->whereColumn(
                            'clients.agence_id',
                            'comptes.agence_id'
                        )
                    )
                    ->with('agence:id,uuid,code_agence,nom_agence'),
            ])
            ->loadCount([
                'comptes' => fn (Builder $comptes): Builder => $comptes
                    ->whereColumn('comptes.agence_id', 'clients.agence_id'),
            ]);
    }

    private function requeteTransactionsComptabilisees(Compte $compte): Builder
    {
        return Transaction::query()
            ->where('statut_transaction', 'terminee')
            ->whereNotNull('date_validation')
            ->whereNotNull('date_transaction')
            ->where(function (Builder $requete) use ($compte): void {
                $requete
                    ->where('compte_source_id', $compte->id)
                    ->orWhere('compte_destinataire_id', $compte->id);
            });
    }

    /**
     * @return array{credits: int, debits: int}
     */
    private function agregerMouvements(
        Compte $compte,
        ?string $limiteInferieure = null,
        ?string $limiteSuperieure = null,
        ?Transaction $limiteTransaction = null
    ): array {
        $requete = $this->requeteTransactionsComptabilisees($compte);

        if ($limiteInferieure !== null) {
            $requete->where('date_transaction', '>=', $limiteInferieure);
        }

        if ($limiteSuperieure !== null) {
            $requete->where('date_transaction', '<', $limiteSuperieure);
        }

        if ($limiteTransaction !== null) {
            $requete->where(function (Builder $avantTransaction) use (
                $limiteTransaction
            ): void {
                $avantTransaction
                    ->where(
                        'date_transaction',
                        '<',
                        $limiteTransaction->date_transaction
                    )
                    ->orWhere(function (Builder $memeDate) use (
                        $limiteTransaction
                    ): void {
                        $memeDate
                            ->where(
                                'date_transaction',
                                $limiteTransaction->date_transaction
                            )
                            ->where('id', '<', $limiteTransaction->id);
                    });
            });
        }

        $credits = 0;

        foreach (
            (clone $requete)
                ->where('compte_destinataire_id', $compte->id)
                ->orderBy('id')
                ->cursor() as $transaction
        ) {
            $credits = $this->ajouterCentimes(
                $credits,
                $this->montantEnCentimes((string) $transaction->montant)
            );
        }

        $debits = 0;

        foreach (
            (clone $requete)
                ->where('compte_source_id', $compte->id)
                ->orderBy('id')
                ->cursor() as $transaction
        ) {
            $debits = $this->ajouterCentimes(
                $debits,
                $this->montantEnCentimes((string) $transaction->montant)
            );
        }

        return [
            'credits' => $credits,
            'debits' => $debits,
        ];
    }

    private function montantEnCentimes(string|int|float $montant): int
    {
        $valeur = (string) $montant;

        if (is_float($montant) || str_contains(strtolower($valeur), 'e')) {
            if (abs((float) $montant) > intdiv(PHP_INT_MAX, 100)) {
                throw new OverflowException('Le solde dépasse la capacité de calcul du relevé.');
            }

            $valeur = number_format((float) $montant, 2, '.', '');
        }

        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $valeur)) {
            throw new \UnexpectedValueException('Un montant comptable est invalide.');
        }

        $negatif = str_starts_with($valeur, '-');
        $valeur = ltrim($valeur, '-');
        [$unites, $decimales] = array_pad(explode('.', $valeur, 2), 2, '0');
        $centimesUnitesMax = (string) intdiv(PHP_INT_MAX, 100);
        $unitesNormalisees = ltrim($unites, '0') ?: '0';

        if (
            strlen($unitesNormalisees) > strlen($centimesUnitesMax)
            || (
                strlen($unitesNormalisees) === strlen($centimesUnitesMax)
                && strcmp($unitesNormalisees, $centimesUnitesMax) > 0
            )
        ) {
            throw new OverflowException('Le solde dépasse la capacité de calcul du relevé.');
        }

        $centimes = ((int) $unitesNormalisees * 100) + (int) str_pad($decimales, 2, '0');

        if ($centimes > PHP_INT_MAX) {
            throw new OverflowException('Le solde dépasse la capacité de calcul du relevé.');
        }

        return $negatif ? -$centimes : $centimes;
    }

    private function ajouterCentimes(int $montant, int $ajout): int
    {
        if (
            ($ajout > 0 && $montant > PHP_INT_MAX - $ajout)
            || ($ajout < 0 && $montant < PHP_INT_MIN - $ajout)
        ) {
            throw new OverflowException('Le solde dépasse la capacité de calcul du relevé.');
        }

        return $montant + $ajout;
    }

    private function formaterCentimes(int $montant): string
    {
        $signe = $montant < 0 ? '-' : '';
        $valeurAbsolue = abs($montant);

        return sprintf(
            '%s%d.%02d',
            $signe,
            intdiv($valeurAbsolue, 100),
            $valeurAbsolue % 100
        );
    }

    private function libelleTransaction(string $type): string
    {
        return match ($type) {
            'depot' => 'Dépôt',
            'retrait' => 'Retrait',
            'virement' => 'Virement',
            'paiement_facture' => 'Paiement de facture',
            'deboursement' => 'Déboursement de crédit',
            'remboursement' => 'Remboursement de crédit',
            default => $type,
        };
    }
}
