<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Compte;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Authorization\AccesAgenceService;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use UnexpectedValueException;
// use Carbon\Carbon;
class OperationsFinancieresService
{
    private const int SEUIL_RETRAIT_XOF_CENTIMES = 200_000_000;

    private const int SOLDE_MAXIMUM_CENTIMES = 999_999_999_999_999;

    public function __construct(
        private readonly AccesAgenceService $accesAgence
    ) {}

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
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    public function trouverTransactionAccessibleParUuid(
        User $utilisateur,
        string $uuid
    ): Transaction {
        return Transaction::query()
            ->accessiblePar($utilisateur)
            ->with($this->relationsTransaction())
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * Liste des transactions avec fiultrage
     * @param array{
     *     reference_transaction?: string,
     *     numero_compte?: string,
     *     client_uuid?: string,
     *     type_transaction?: string,
     *     statut_transaction?: string,
     *     date_debut?: string,
     *     date_fin?: string,
     *     agence_id?: int,
     *     per_page?: int
     * } $filtres
     */
    public function listerTransactions(
        User $utilisateur,
        array $filtres,
        bool $retraitsEnAttenteUniquement = false
    ): LengthAwarePaginator {
        $requete = Transaction::query()
            ->accessiblePar($utilisateur)
            ->with($this->relationsTransaction());

        if ($retraitsEnAttenteUniquement) {
            $requete
                ->where('type_transaction', 'retrait')
                ->where('statut_transaction', 'en_attente')
                ->where('devise', 'XOF')
                ->where('montant', '>', '2000000.00');
        }

        return $requete
            ->when(
                isset($filtres['reference_transaction']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'reference_transaction',
                    'like',
                    '%'.$filtres['reference_transaction'].'%'
                )
            )
            ->when(
                isset($filtres['numero_compte']),
                function (Builder $transactions) use ($filtres): void {
                    $transactions->where(function (Builder $comptes) use ($filtres): void {
                        $comptes
                            ->whereHas(
                                'compteSource',
                                fn (Builder $compte): Builder => $compte->where(
                                    'numero_compte',
                                    'like',
                                    '%'.$filtres['numero_compte'].'%'
                                )
                            )
                            ->orWhereHas(
                                'compteDestinataire',
                                fn (Builder $compte): Builder => $compte->where(
                                    'numero_compte',
                                    'like',
                                    '%'.$filtres['numero_compte'].'%'
                                )
                            );
                    });
                }
            )
            ->when(
                isset($filtres['client_uuid']),
                fn (Builder $transactions): Builder => $transactions->whereHas(
                    'client',
                    fn (Builder $client): Builder => $client->where(
                        'uuid',
                        $filtres['client_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['type_transaction']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'type_transaction',
                    $filtres['type_transaction']
                )
            )
            ->when(
                isset($filtres['statut_transaction']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'statut_transaction',
                    $filtres['statut_transaction']
                )
            )
            ->when(
                isset($filtres['date_debut']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'date_transaction',
                    '>=',
                    $filtres['date_debut'].' 00:00:00'
                )
            )
            ->when(
                isset($filtres['date_fin']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'date_transaction',
                    '<',
                    Carbon::parse($filtres['date_fin'])->addDay()->toDateString().' 00:00:00'
                )
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $transactions): Builder => $transactions->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->orderByDesc('date_transaction')
            ->orderByDesc('id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    /**
     * @return array{transaction: Transaction, rejouee: bool}
     * Depot d'argent sur un compte client
     */
    public function deposer(
        User $utilisateur,
        Compte $compteCible,
        string $montant,
        ?string $motif,
        string $cleIdempotence
    ): array {
        $empreinte = $this->empreinteOperation(
            $utilisateur,
            'depot',
            $compteCible->id,
            null,
            $montant,
            $motif,
            $compteCible->devise
        );

        return $this->avecIdempotence(
            $cleIdempotence,
            $empreinte,
            function () use (
                $utilisateur,
                $compteCible,
                $montant,
                $motif,
                $cleIdempotence,
                $empreinte
            ): array {
                $compte = $this->verrouillerComptes(
                    [$compteCible->id],
                    $utilisateur
                )->first();

                if ($rejeu = $this->rejouerSiExistante($cleIdempotence, $empreinte)) {
                    return $rejeu;
                }

                $this->verifierCompteActif($compte);
                $client = $this->verrouillerClientsPourComptes(
                    [$compte],
                    $utilisateur
                )->first();
                $nouveauSolde = $this->calculerNouveauSolde(
                    $compte,
                    $this->additionnerCentimes(
                        $this->montantEnCentimes((string) $compte->solde),
                        $this->montantEnCentimes($montant)
                    )
                );

                $transaction = $this->creerTransaction(
                    $utilisateur,
                    $compte,
                    'depot',
                    $montant,
                    null,
                    $compte->devise,
                    'terminee',
                    $cleIdempotence,
                    $empreinte,
                    $motif,
                    $utilisateur
                );

                $compte->forceFill(['solde' => $nouveauSolde])->save();
                $transaction->load($this->relationsTransaction());

                return ['transaction' => $transaction, 'rejouee' => false];
            }
        );
    }

    /**
     * @return array{transaction: Transaction, rejouee: bool}
     * Retrait d'argent sur un compte client
     */
    public function retirer(
        User $utilisateur,
        Compte $compteCible,
        string $montant,
        ?string $motif,
        string $cleIdempotence
    ): array {
        $empreinte = $this->empreinteOperation(
            $utilisateur,
            'retrait',
            $compteCible->id,
            null,
            $montant,
            $motif,
            $compteCible->devise
        );

        return $this->avecIdempotence(
            $cleIdempotence,
            $empreinte,
            function () use (
                $utilisateur,
                $compteCible,
                $montant,
                $motif,
                $cleIdempotence,
                $empreinte
            ): array {
                $compte = $this->verrouillerComptes(
                    [$compteCible->id],
                    $utilisateur
                )->first();

                if ($rejeu = $this->rejouerSiExistante($cleIdempotence, $empreinte)) {
                    return $rejeu;
                }

                $this->verifierCompteActif($compte);
                $client = $this->verrouillerClientsPourComptes(
                    [$compte],
                    $utilisateur
                )->first();
                $this->verifierClientActif($client);

                $montantEnCentimes = $this->montantEnCentimes($montant);
                $solde = $this->montantEnCentimes((string) $compte->solde);
                $this->verifierSoldeSuffisant($solde, $montantEnCentimes);

                $retraitEnAttente = $compte->devise === 'XOF'
                    && $montantEnCentimes > self::SEUIL_RETRAIT_XOF_CENTIMES;
                $nouveauStatut = $retraitEnAttente ? 'en_attente' : 'terminee';

                $transaction = $this->creerTransaction(
                    $utilisateur,
                    $compte,
                    'retrait',
                    $montant,
                    null,
                    $compte->devise,
                    $nouveauStatut,
                    $cleIdempotence,
                    $empreinte,
                    $motif,
                    $retraitEnAttente ? null : $utilisateur
                );

                if (! $retraitEnAttente) {
                    $compte->forceFill([
                        'solde' => $this->calculerNouveauSolde(
                            $compte,
                            $this->additionnerCentimes($solde, -$montantEnCentimes)
                        ),
                    ])->save();
                }

                $transaction->load($this->relationsTransaction());

                return ['transaction' => $transaction, 'rejouee' => false];
            }
        );
    }

    /**
     * @return array{transaction: Transaction, rejouee: bool}
     * Faire un virement entre deux comptes client
     */
    public function virer(
        User $utilisateur,
        Compte $compteSourceCible,
        Compte $compteDestinataireCible,
        string $montant,
        ?string $motif,
        string $cleIdempotence
    ): array {
        $empreinte = $this->empreinteOperation(
            $utilisateur,
            'virement',
            $compteSourceCible->id,
            $compteDestinataireCible->id,
            $montant,
            $motif,
            $compteSourceCible->devise
        );

        return $this->avecIdempotence(
            $cleIdempotence,
            $empreinte,
            function () use (
                $utilisateur,
                $compteSourceCible,
                $compteDestinataireCible,
                $montant,
                $motif,
                $cleIdempotence,
                $empreinte
            ): array {
                if ($compteSourceCible->id === $compteDestinataireCible->id) {
                    throw new HttpException(422, 'Le compte source et le compte destinataire doivent être différents.');
                }

                $comptes = $this->verrouillerComptes(
                    [$compteSourceCible->id, $compteDestinataireCible->id],
                    $utilisateur
                );
                $compteSource = $comptes->get($compteSourceCible->id);
                $compteDestinataire = $comptes->get($compteDestinataireCible->id);

                if ($compteSource->agence_id !== $compteDestinataire->agence_id) {
                    throw new HttpException(404, 'Compte introuvable.');
                }

                if ($rejeu = $this->rejouerSiExistante($cleIdempotence, $empreinte)) {
                    return $rejeu;
                }

                $this->verifierCompteActif($compteSource);
                $this->verifierCompteActif($compteDestinataire);

                if ($compteSource->devise !== $compteDestinataire->devise) {
                    throw new HttpException(422, 'Les comptes doivent utiliser la même devise.');
                }

                $clients = $this->verrouillerClientsPourComptes(
                    $comptes,
                    $utilisateur
                );
                $this->verifierClientActif($clients->get($compteSource->client_id));
                $this->verifierClientActif($clients->get($compteDestinataire->client_id));

                $montantEnCentimes = $this->montantEnCentimes($montant);
                $soldeSource = $this->montantEnCentimes((string) $compteSource->solde);
                $soldeDestinataire = $this->montantEnCentimes(
                    (string) $compteDestinataire->solde
                );
                $this->verifierSoldeSuffisant($soldeSource, $montantEnCentimes);
                $nouveauSoldeSource = $this->calculerNouveauSolde(
                    $compteSource,
                    $this->additionnerCentimes($soldeSource, -$montantEnCentimes)
                );
                $nouveauSoldeDestinataire = $this->calculerNouveauSolde(
                    $compteDestinataire,
                    $this->additionnerCentimes($soldeDestinataire, $montantEnCentimes)
                );

                $transaction = $this->creerTransaction(
                    $utilisateur,
                    $compteSource,
                    'virement',
                    $montant,
                    $compteDestinataire,
                    $compteSource->devise,
                    'terminee',
                    $cleIdempotence,
                    $empreinte,
                    $motif,
                    $utilisateur
                );

                $compteSource->forceFill(['solde' => $nouveauSoldeSource])->save();
                $compteDestinataire->forceFill([
                    'solde' => $nouveauSoldeDestinataire,
                ])->save();
                $transaction->load($this->relationsTransaction());

                return ['transaction' => $transaction, 'rejouee' => false];
            }
        );
    }

    // Approuver un retrait en attente
    public function approuverRetrait(User $validateur, string $uuid): Transaction
    {
        return DB::transaction(function () use ($validateur, $uuid): Transaction {
            $candidat = $this->trouverTransactionAccessibleParUuid($validateur, $uuid);
            $comptes = $this->verrouillerComptes([$candidat->compte_source_id], $validateur);
            $compte = $comptes->first();
            $client = $this->verrouillerClientsPourComptes(
                $comptes,
                $validateur
            )->first();
            $transaction = Transaction::query()
                ->whereKey($candidat->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($validateur)->authorize('validerRetrait', $transaction);
            $this->verifierCompteActif($compte);
            $this->verifierClientActif($client);

            $montant = $this->montantEnCentimes((string) $transaction->montant);
            $solde = $this->montantEnCentimes((string) $compte->solde);
            $this->verifierSoldeSuffisant($solde, $montant);

            $compte->forceFill([
                'solde' => $this->calculerNouveauSolde(
                    $compte,
                    $this->additionnerCentimes($solde, -$montant)
                ),
            ])->save();

            $transaction->forceFill([
                'statut_transaction' => 'terminee',
                'valide_par' => $validateur->id,
                'date_validation' => now(),
            ])->save();

            return $transaction->load($this->relationsTransaction());
        }, 3);
    }

    // Rejeter un retrait en attente
    public function rejeterRetrait(
        User $validateur,
        string $uuid,
        ?string $motif
    ): Transaction {
        return DB::transaction(function () use (
            $validateur,
            $uuid,
            $motif
        ): Transaction {
            $candidat = $this->trouverTransactionAccessibleParUuid($validateur, $uuid);
            $comptes = $this->verrouillerComptes([$candidat->compte_source_id], $validateur);
            $this->verrouillerClientsPourComptes($comptes, $validateur);
            $transaction = Transaction::query()
                ->whereKey($candidat->id)
                ->lockForUpdate()
                ->firstOrFail();

            Gate::forUser($validateur)->authorize('validerRetrait', $transaction);

            $motifRejet = trim((string) $motif);
            $motifComplet = $motifRejet === ''
                ? $transaction->motif
                : trim((string) $transaction->motif."\nMotif du rejet : ".$motifRejet);

            $transaction->forceFill([
                'statut_transaction' => 'annulee',
                'valide_par' => $validateur->id,
                'date_validation' => now(),
                'motif' => $motifComplet,
            ])->save();

            return $transaction->load($this->relationsTransaction());
        }, 3);
    }

    // Contrainte d'unicité sur la clé d'idempotence pour rejouer une opération
    private function avecIdempotence(
        string $cleIdempotence,
        string $empreinte,
        Closure $operation
    ): array {
        try {
            return DB::transaction($operation, 3);
        } catch (QueryException $exception) {
            if (! str_contains($exception->getMessage(), 'cle_idempotence')) {
                throw $exception;
            }

            $transaction = Transaction::withTrashed()
                ->where('cle_idempotence', $cleIdempotence)
                ->first();

            if (! $transaction) {
                throw $exception;
            }

            return $this->resultatRejoue($transaction, $empreinte);
        }
    }

    /**
     * @return array{transaction: Transaction, rejouee: bool}|null
     * Vérifie si une opération avec la même clé d'idempotence a déjà été effectuée
     */
    private function rejouerSiExistante(
        string $cleIdempotence,
        string $empreinte
    ): ?array {
        $transaction = Transaction::withTrashed()
            ->where('cle_idempotence', $cleIdempotence)
            ->first();

        return $transaction
            ? $this->resultatRejoue($transaction, $empreinte)
            : null;
    }

    /**
     * @return array{transaction: Transaction, rejouee: bool}
     * Résultat d'une opération rejouée avec la même clé d'idempotence
     */
    private function resultatRejoue(
        Transaction $transaction,
        string $empreinte
    ): array {
        if (
            $transaction->trashed()
            || ! hash_equals(
                (string) data_get($transaction->metadata, 'idempotence.empreinte'),
                $empreinte
            )
        ) {
            throw new HttpException(
                409,
                'Cette clé d’idempotence a déjà été utilisée avec une autre opération.'
            );
        }

        return [
            'transaction' => $transaction->load($this->relationsTransaction()),
            'rejouee' => true,
        ];
    }

    // Verrouille les comptes pour éviter les conditions de concurrence
    private function verrouillerComptes(
        array $identifiants,
        User $utilisateur
    ): Collection {
        $identifiants = array_values(array_unique(array_map('intval', $identifiants)));
        sort($identifiants);

        $comptes = Compte::query()
            ->accessiblePar($utilisateur)
            ->whereIn('id', $identifiants)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($comptes->count() !== count($identifiants)) {
            throw new HttpException(404, 'Compte introuvable.');
        }

        foreach ($comptes as $compte) {
            if (! $this->accesAgence->peutAccederAgence($utilisateur, $compte->agence_id)) {
                throw new HttpException(404, 'Compte introuvable.');
            }

            if (
                ! $utilisateur->role->roleEchelleDuReseau()
                && (int) $compte->agence_id !== (int) $utilisateur->agence_id
            ) {
                throw new HttpException(404, 'Compte introuvable.');
            }
        }

        return $comptes;
    }

    private function verrouillerClientsPourComptes(
        Collection|array $comptes,
        User $utilisateur
    ): Collection {
        $comptes = collect($comptes);
        $identifiants = $comptes
            ->pluck('client_id')
            ->map(fn ($identifiant): int => (int) $identifiant)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $clients = Client::query()
            ->accessiblePar($utilisateur)
            ->whereIn('id', $identifiants)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($clients->count() !== count($identifiants)) {
            throw new HttpException(404, 'Client introuvable.');
        }

        foreach ($comptes as $compte) {
            $client = $clients->get($compte->client_id);

            if ((int) $client->agence_id !== (int) $compte->agence_id) {
                throw new HttpException(404, 'Compte introuvable.');
            }
        }

        return $clients;
    }

    private function verifierCompteActif(Compte $compte): void
    {
        if ($compte->statut_compte !== 'actif') {
            throw new HttpException(422, 'Le compte n’est pas actif.');
        }
    }

    private function verifierClientActif(Client $client): void
    {
        if ($client->statut !== 'actif') {
            throw new HttpException(422, 'Le client n’est pas actif.');
        }
    }

    private function verifierSoldeSuffisant(int $solde, int $montant): void
    {
        if ($montant <= 0) {
            throw new HttpException(422, 'Le montant doit être strictement positif.');
        }

        if ($solde < $montant) {
            throw new HttpException(422, 'Le solde disponible est insuffisant.');
        }
    }

    private function calculerNouveauSolde(Compte $compte, int $solde): string
    {
        if ($solde < 0) {
            throw new HttpException(422, 'Le solde du compte ne peut pas être négatif.');
        }

        if ($solde > self::SOLDE_MAXIMUM_CENTIMES) {
            throw new HttpException(422, 'Le solde dépasse la capacité du compte.');
        }

        return $this->formaterCentimes($solde);
    }

    private function creerTransaction(
        User $utilisateur,
        Compte $compteSource,
        string $type,
        string $montant,
        ?Compte $compteDestinataire,
        string $devise,
        string $statut,
        string $cleIdempotence,
        string $empreinte,
        ?string $motif,
        ?User $validateur
    ): Transaction {
        return Transaction::query()->create([
            'reference_transaction' => $this->genererReference(),
            'agence_id' => $compteSource->agence_id,
            'client_id' => $compteSource->client_id,
            'user_id' => $utilisateur->id,
            'type_transaction' => $type,
            'montant' => $this->formaterCentimes(
                $this->montantEnCentimes($montant)
            ),
            'devise' => $devise,
            'date_transaction' => now(),
            'compte_source_id' => $type === 'depot' ? null : $compteSource->id,
            'compte_destinataire_id' => $type === 'depot'
                ? $compteSource->id
                : $compteDestinataire?->id,
            'statut_transaction' => $statut,
            'valide_par' => $validateur?->id,
            'date_validation' => $validateur ? now() : null,
            'cle_idempotence' => $cleIdempotence,
            'motif' => $motif,
            'metadata' => [
                'idempotence' => ['empreinte' => $empreinte],
            ],
        ]);
    }

    private function genererReference(): string
    {
        do {
            $reference = 'TRX-'.Str::upper(Str::random(26));
        } while (Transaction::withTrashed()
            ->where('reference_transaction', $reference)
            ->exists());

        return $reference;
    }

    private function empreinteOperation(
        User $utilisateur,
        string $type,
        int $compteSourceId,
        ?int $compteDestinataireId,
        string $montant,
        ?string $motif,
        string $devise
    ): string {
        return hash('sha256', json_encode([
            'type' => $type,
            'utilisateur_id' => $utilisateur->id,
            'agence_id' => $utilisateur->agence_id,
            'compte_source_id' => $compteSourceId,
            'compte_destinataire_id' => $compteDestinataireId,
            'montant_centimes' => $this->montantEnCentimes($montant),
            'devise' => $devise,
            'motif' => $motif,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, string>
     */
    private function relationsTransaction(): array
    {
        return [
            'agence:id,uuid,code_agence,nom_agence',
            'client:id,uuid,code_client,nom_client,prenom_client',
            'user:id,uuid,nom,prenom',
            'validateur:id,uuid,nom,prenom',
            'compteSource:id,uuid,numero_compte,type_compte',
            'compteDestinataire:id,uuid,numero_compte,type_compte',
        ];
    }

    private function montantEnCentimes(string|int|float $montant): int
    {
        $valeur = (string) $montant;

        if (is_float($montant) || str_contains(strtolower($valeur), 'e')) {
            if (abs((float) $montant) > intdiv(self::SOLDE_MAXIMUM_CENTIMES, 100)) {
                throw new UnexpectedValueException('Un montant dépasse la capacité comptable.');
            }

            $valeur = number_format((float) $montant, 2, '.', '');
        }

        if (! preg_match('/^\d{1,13}(?:\.\d{1,2})?$/', $valeur)) {
            throw new UnexpectedValueException('Un montant comptable est invalide.');
        }

        [$unites, $decimales] = array_pad(explode('.', $valeur, 2), 2, '0');
        $centimes = ((int) $unites * 100) + (int) str_pad($decimales, 2, '0');

        if ($centimes > self::SOLDE_MAXIMUM_CENTIMES) {
            throw new UnexpectedValueException('Un montant dépasse la capacité comptable.');
        }

        return $centimes;
    }

    private function additionnerCentimes(int $solde, int $mouvement): int
    {
        if (
            ($mouvement > 0 && $solde > PHP_INT_MAX - $mouvement)
            || ($mouvement < 0 && $solde < PHP_INT_MIN - $mouvement)
        ) {
            throw new HttpException(422, 'Le solde dépasse la capacité de calcul.');
        }

        return $solde + $mouvement;
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
