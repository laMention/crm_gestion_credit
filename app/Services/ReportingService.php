<?php

namespace App\Services;

use App\Models\Agence;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\Reporting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

// use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
class ReportingService
{
    private const array TYPES_TRANSACTION = [
        'depot',
        'retrait',
        'virement',
        // 'paiement_facture',
        'deboursement',
        'remboursement',
    ];

    public function __construct(
        private readonly AuditLogService $journalAudit
    ) {}

    /**
     * @param  array<string, mixed>  $filtres
     * @return array<string, mixed>
     */
    public function indicateurs(User $utilisateur, array $filtres): array
    {
        [$dateDebut, $dateFin] = $this->periode($filtres);
        $statsTransactions = $this->agregerTransactions(
            $utilisateur,
            $filtres,
            $dateDebut,
            $dateFin
        );
        $clients = Client::query();
        $comptes = Compte::query();
        $credits = Credit::query();
        $echeances = EcheanceCredit::query();

        $this->filtrerAgence($clients, $utilisateur, $filtres);
        $this->filtrerAgence($comptes, $utilisateur, $filtres);
        $this->filtrerAgenceCredit($credits, $utilisateur, $filtres);
        $this->filtrerAgenceEcheance($echeances, $utilisateur, $filtres);

        $clientsNouveaux = (clone $clients)
            ->whereBetween('created_at', $this->bornesHorodatage($dateDebut, $dateFin))
            ->count();
        $echeancesEnRetard = (clone $echeances)
            ->whereDate('date_echeance', '<', today())
            ->whereColumn('montant_paye', '<', 'montant_echeance')
            ->where('statut_echeance_credit', '!=', 'annule');
        $creditsDeboursesPeriode = (clone $credits)
            ->whereDate('date_deboursement', '>=', $dateDebut)
            ->whereDate('date_deboursement', '<=', $dateFin);
        $approbationsPeriode = (clone $credits)
            ->whereDate('date_approbation', '>=', $dateDebut)
            ->whereDate('date_approbation', '<=', $dateFin);

        $creditsDebourses = $creditsDeboursesPeriode->count();
        $capitalRestant = $this->sommeEnCentimes(
            (clone $credits)
                ->whereNotNull('date_deboursement')
                ->whereIn('statut', ['debourse', 'en_remboursement', 'en_retard'])
                ->select(['capital_restant_du'])
                ->cursor()
                ->pluck('capital_restant_du')
        );
        $montantEcheancesImpayees = $this->montantRestantEcheances(
            (clone $echeances)
                ->whereColumn('montant_paye', '<', 'montant_echeance')
                ->where('statut_echeance_credit', '!=', 'annule')
                ->select(['montant_echeance', 'montant_paye'])
                ->cursor()
        );

        return [
            'periode' => [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
            ],
            'perimetre' => $this->perimetre($utilisateur, $filtres),
            'indicateurs_generaux' => [
                'nombre_total_clients' => (clone $clients)->count(),
                'nombre_nouveaux_clients' => $clientsNouveaux,
                'nombre_total_comptes' => (clone $comptes)->count(),
                'nombre_comptes_actifs' => (clone $comptes)
                    ->where('statut_compte', 'actif')
                    ->count(),
                'nombre_comptes_bloques' => (clone $comptes)
                    ->where('statut_compte', 'bloque')
                    ->count(),
                'nombre_comptes_clotures' => (clone $comptes)
                    ->where('statut_compte', 'cloture')
                    ->count(),
                'nombre_total_transactions' => $statsTransactions['nombre_total'],
                'volume_brut_operations' => $statsTransactions['volume_brut'],
                'flux_net_hors_virements' => $statsTransactions['flux_net'],
                'nombre_credits_en_cours' => (clone $credits)
                    ->whereIn('statut', ['debourse', 'en_remboursement', 'en_retard'])
                    ->count(),
                'nombre_credits_debourses_sur_periode' => $creditsDebourses,
                'montant_total_credits_debourses_sur_periode' => $statsTransactions[
                    'par_type'
                ]['deboursement']['montant_total'],
                'nombre_credits_soldes' => (clone $credits)
                    ->where('statut', 'solde')
                    ->count(),
                'capital_restant_du' => $this->formaterCentimes($capitalRestant),
                'nombre_echeances_en_retard' => (clone $echeancesEnRetard)->count(),
                'montant_total_echeances_impayees' => $this->formaterCentimes(
                    $montantEcheancesImpayees
                ),
                'montant_total_remboursements_sur_periode' => $statsTransactions[
                    'par_type'
                ]['remboursement']['montant_total'],
            ],
            'indicateurs_credits' => [
                'demandes_en_instruction' => (clone $credits)
                    ->where('statut', 'en_etude')
                    ->count(),
                'demandes_en_attente_validation' => (clone $credits)
                    ->where('statut', 'en_attente')
                    ->count(),
                'credits_approuves_sur_periode' => (clone $approbationsPeriode)
                    ->whereNull('motif_rejet')
                    ->count(),
                'credits_rejetes_sur_periode' => (clone $approbationsPeriode)
                    ->whereNotNull('motif_rejet')
                    ->count(),
                'credits_debourses_sur_periode' => $creditsDebourses,
                'credits_soldes' => (clone $credits)
                    ->where('statut', 'solde')
                    ->count(),
                'credits_avec_echeances_en_retard' => (clone $credits)
                    ->whereHas(
                        'echeances',
                        fn (Builder $requete): Builder => $requete
                            ->whereDate('date_echeance', '<', today())
                            ->whereColumn('montant_paye', '<', 'montant_echeance')
                            ->where('statut_echeance_credit', '!=', 'annule')
                    )
                    ->count(),
                'credits_avec_echeances_impayees_plus_de_30_jours' => (clone $credits)
                    ->whereHas(
                        'echeances',
                        fn (Builder $requete): Builder => $requete
                            ->whereDate('date_echeance', '<', today()->subDays(30))
                            ->whereColumn('montant_paye', '<', 'montant_echeance')
                            ->where('statut_echeance_credit', '!=', 'annule')
                    )
                    ->count(),
            ],
            'transactions' => $statsTransactions['par_type'],
        ];
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function listerReportings(
        User $utilisateur,
        array $filtres
    ): LengthAwarePaginator {
        [$dateDebut, $dateFin] = $this->periode($filtres);

        return Reporting::query()
            ->accessiblePar($utilisateur)
            ->when(
                isset($filtres['agence_id']) && $utilisateur->role->roleEchelleDuReseau(),
                fn (Builder $requete): Builder => $requete->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->whereDate('date_reporting', '>=', $dateDebut)
            ->whereDate('date_reporting', '<=', $dateFin)
            ->with('agence:id,uuid,code_agence,nom_agence')
            ->orderByDesc('date_reporting')
            ->orderBy('agence_id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function trouverReportingAccessible(User $utilisateur, string $uuid): Reporting
    {
        return Reporting::query()
            ->accessiblePar($utilisateur)
            ->with('agence:id,uuid,code_agence,nom_agence')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function listerTransactions(
        User $utilisateur,
        array $filtres
    ): LengthAwarePaginator {
        [$dateDebut, $dateFin] = $this->periode($filtres);
        $requete = $this->requeteTransactions(
            $utilisateur,
            $filtres,
            $dateDebut,
            $dateFin
        );

        return $requete
            ->select([
                'id',
                'uuid',
                'reference_transaction',
                'agence_id',
                'type_transaction',
                'montant',
                'devise',
                'date_transaction',
            ])
            ->orderByDesc('date_transaction')
            ->orderByDesc('id')
            ->paginate($filtres['per_page'] ?? 25);
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return array{indicateurs: array<string, mixed>, transactions: Builder}
     */
    public function preparerExport(User $utilisateur, array $filtres): array
    {
        Gate::forUser($utilisateur)->authorize('exporterTout', Reporting::class);
        [$dateDebut, $dateFin] = $this->periode($filtres);
        $indicateurs = $this->indicateurs($utilisateur, $filtres);

        $this->journalAudit->enregistrer(
            $utilisateur,
            'reporting.exporte',
            'reportings',
            'rapport',
            null,
            [],
            [
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'agence_id' => $filtres['agence_id'] ?? $utilisateur->agence_id,
                'type_transaction' => $filtres['type_transaction'] ?? null,
            ],
            description: 'Export CSV des indicateurs de reporting.'
        );

        return [
            'indicateurs' => $indicateurs,
            'transactions' => $this->requeteTransactions(
                $utilisateur,
                $filtres,
                $dateDebut,
                $dateFin
            )->orderBy('date_transaction')->orderBy('id'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtres
     */
    public function genererReporting(User $utilisateur, array $filtres): Reporting
    {
        Gate::forUser($utilisateur)->authorize('consulterTout', Reporting::class);
        $agenceId = $utilisateur->role->estPersonnelAgence()
            ? $utilisateur->agence_id
            : (int) $filtres['agence_id'];
        $dateReporting = Carbon::createFromFormat(
            'Y-m-d',
            $filtres['date_reporting'] ?? today()->toDateString()
        )->toDateString();

        try {
            return DB::transaction(function () use (
                $utilisateur,
                $agenceId,
                $dateReporting
            ): Reporting {
                $agence = Agence::query()
                    ->whereKey($agenceId)
                    ->lockForUpdate()
                    ->firstOrFail();
                $indicateurs = $this->indicateurs($utilisateur, [
                    'date_debut' => $dateReporting,
                    'date_fin' => $dateReporting,
                    'agence_id' => $agence->id,
                ]);
                $general = $indicateurs['indicateurs_generaux'];
                $transactions = $indicateurs['transactions'];

                $reporting = Reporting::query()
                    ->where('agence_id', $agence->id)
                    ->whereDate('date_reporting', $dateReporting)
                    ->first();

                if ($reporting === null) {
                    $reporting = new Reporting([
                        'agence_id' => $agence->id,
                        'date_reporting' => $dateReporting,
                    ]);
                }

                $reporting->fill([
                    'total_depots' => $transactions['depot']['montant_total'],
                    'total_retraits' => $transactions['retrait']['montant_total'],
                    'total_virements' => $transactions['virement']['montant_total'],
                    'total_deboursements' => $transactions['deboursement']['montant_total'],
                    'total_remboursements' => $transactions['remboursement']['montant_total'],
                    'nombre_depots' => $transactions['depot']['nombre'],
                    'nombre_retraits' => $transactions['retrait']['nombre'],
                    'nombre_virements' => $transactions['virement']['nombre'],
                    'nombre_credits_debourses' => $general[
                        'nombre_credits_debourses_sur_periode'
                    ],
                    'nombre_remboursements' => $transactions['remboursement']['nombre'],
                    'nombre_nouveaux_clients' => $general['nombre_nouveaux_clients'],
                    'nombre_credits_en_cours' => $general['nombre_credits_en_cours'],
                    'encours_credits' => $general['capital_restant_du'],
                    'indicateurs' => $indicateurs,
                    'genere_le' => now(),
                    'statut_generation' => 'termine',
                ]);
                $reporting->save();

                $this->journalAudit->enregistrer(
                    $utilisateur,
                    'reporting.genere',
                    'reportings',
                    'rapport',
                    $reporting->id,
                    [],
                    ['date_reporting' => $dateReporting, 'agence_id' => $agence->id],
                    agenceId: $agence->id,
                    description: 'Reporting quotidien généré ou recalculé.'
                );

                return $reporting->load('agence:id,uuid,code_agence,nom_agence');
            }, 3);
        } catch (Throwable $exception) {
            $this->journalAudit->enregistrer(
                $utilisateur,
                'reporting.generation_echouee',
                'reportings',
                'rapport',
                null,
                [],
                ['date_reporting' => $dateReporting],
                'echec',
                $agenceId,
                'Échec de génération du reporting quotidien.'
            );

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $filtres
     * @return array{
     *   nombre_total: int,
     *   volume_brut: string,
     *   flux_net: string,
     *   par_type: array<string, array{nombre: int, montant_total: string, montant_moyen: string}>
     * }
     */
    private function agregerTransactions(
        User $utilisateur,
        array $filtres,
        string $dateDebut,
        string $dateFin
    ): array {
        $parType = [];
        foreach (self::TYPES_TRANSACTION as $type) {
            $parType[$type] = ['nombre' => 0, 'centimes' => 0];
        }

        $nombreTotal = 0;
        $volumeBrut = 0;
        $fluxNet = 0;
        $requete = $this->requeteTransactions($utilisateur, $filtres, $dateDebut, $dateFin)
            ->select(['type_transaction', 'montant'])
            ->orderBy('id');

        foreach ($requete->cursor() as $transaction) {
            $type = $transaction->type_transaction;
            if (! isset($parType[$type])) {
                continue;
            }

            $centimes = $this->centimes((string) $transaction->montant);
            $parType[$type]['nombre']++;
            $parType[$type]['centimes'] += $centimes;
            $nombreTotal++;
            $volumeBrut += $centimes;

            if (in_array($type, ['depot', 'deboursement'], true)) {
                $fluxNet += $centimes;
            } elseif (in_array(
                $type,
                // ['retrait', 'remboursement', 'paiement_facture'],
                ['retrait', 'remboursement'],
                true
            )) {
                $fluxNet -= $centimes;
            }
        }

        foreach ($parType as $type => $statistique) {
            $parType[$type] = [
                'nombre' => $statistique['nombre'],
                'montant_total' => $this->formaterCentimes($statistique['centimes']),
                'montant_moyen' => $statistique['nombre'] === 0
                    ? '0.00'
                    : $this->formaterCentimes(
                        intdiv(
                            $statistique['centimes'] + intdiv($statistique['nombre'], 2),
                            $statistique['nombre']
                        )
                    ),
            ];
        }

        return [
            'nombre_total' => $nombreTotal,
            'volume_brut' => $this->formaterCentimes($volumeBrut),
            'flux_net' => $this->formaterCentimes($fluxNet),
            'par_type' => $parType,
        ];
    }

    private function requeteTransactions(
        User $utilisateur,
        array $filtres,
        string $dateDebut,
        string $dateFin
    ): Builder {
        return Transaction::query()
            ->accessiblePar($utilisateur)
            ->where('statut_transaction', 'terminee')
            ->whereNotNull('date_validation')
            ->where('date_transaction', '>=', $dateDebut.' 00:00:00')
            ->where(
                'date_transaction',
                '<',
                Carbon::parse($dateFin)->addDay()->toDateString().' 00:00:00'
            )
            ->when(
                isset($filtres['type_transaction']),
                fn (Builder $requete): Builder => $requete->where(
                    'type_transaction',
                    $filtres['type_transaction']
                )
            )
            ->when(
                isset($filtres['agence_id']) && $utilisateur->role->roleEchelleDuReseau(),
                fn (Builder $requete): Builder => $requete->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            );
    }

    private function filtrerAgence(
        Builder $requete,
        User $utilisateur,
        array $filtres
    ): void {
        if ($utilisateur->role->estPersonnelAgence()) {
            $requete->where('agence_id', $utilisateur->agence_id);

            return;
        }

        if (isset($filtres['agence_id'])) {
            $requete->where('agence_id', $filtres['agence_id']);
        }
    }

    private function filtrerAgenceCredit(
        Builder $requete,
        User $utilisateur,
        array $filtres
    ): void {
        if ($utilisateur->role->estPersonnelAgence()) {
            $requete->whereHas(
                'compte',
                fn (Builder $comptes): Builder => $comptes->where(
                    'agence_id',
                    $utilisateur->agence_id
                )
            );

            return;
        }

        if (isset($filtres['agence_id'])) {
            $requete->whereHas(
                'compte',
                fn (Builder $comptes): Builder => $comptes->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            );
        }
    }

    private function filtrerAgenceEcheance(
        Builder $requete,
        User $utilisateur,
        array $filtres
    ): void {
        if ($utilisateur->role->estPersonnelAgence()) {
            $requete->whereHas(
                'credit.compte',
                fn (Builder $comptes): Builder => $comptes->where(
                    'agence_id',
                    $utilisateur->agence_id
                )
            );

            return;
        }

        if (isset($filtres['agence_id'])) {
            $requete->whereHas(
                'credit.compte',
                fn (Builder $comptes): Builder => $comptes->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periode(array $filtres): array
    {
        $dateFin = Carbon::parse($filtres['date_fin'] ?? today())->toDateString();
        $dateDebut = Carbon::parse($filtres['date_debut'] ?? $dateFin)->toDateString();

        return [$dateDebut, $dateFin];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bornesHorodatage(string $dateDebut, string $dateFin): array
    {
        return [
            $dateDebut.' 00:00:00',
            Carbon::parse($dateFin)->endOfDay()->toDateTimeString(),
        ];
    }

    private function perimetre(User $utilisateur, array $filtres): string
    {
        if ($utilisateur->role->estPersonnelAgence()) {
            return 'agence:'.$utilisateur->agence_id;
        }

        return isset($filtres['agence_id'])
            ? 'agence:'.$filtres['agence_id']
            : 'reseau';
    }

    /**
     * @param  iterable<mixed>  $montants
     */
    private function sommeEnCentimes(iterable $montants): int
    {
        $total = 0;
        foreach ($montants as $montant) {
            $total += $this->centimes((string) $montant);
        }

        return $total;
    }

    /**
     * @param  iterable<object>  $echeances
     */
    private function montantRestantEcheances(iterable $echeances): int
    {
        $total = 0;
        foreach ($echeances as $echeance) {
            $total += max(
                0,
                $this->centimes((string) $echeance->montant_echeance)
                    - $this->centimes((string) $echeance->montant_paye)
            );
        }

        return $total;
    }

    private function centimes(string $montant): int
    {
        [$unites, $decimales] = array_pad(explode('.', $montant, 2), 2, '0');

        return ((int) $unites * 100) + (int) str_pad($decimales, 2, '0');
    }

    private function formaterCentimes(int $montant): string
    {
        $signe = $montant < 0 ? '-' : '';
        $valeurAbsolue = abs($montant);

        return $signe.sprintf(
            '%d.%02d',
            intdiv($valeurAbsolue, 100),
            $valeurAbsolue % 100
        );
    }
}
