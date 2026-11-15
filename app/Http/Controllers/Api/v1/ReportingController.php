<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FiltrerReportingsRequest;
use App\Http\Requests\Api\V1\GenererReportingRequest;
use App\Models\Reporting;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function __construct(
        private readonly ReportingService $reportingService
    ) {}

    public function indicateurs(FiltrerReportingsRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportingService->indicateurs(
                $request->user(),
                $request->validated()
            ),
        ]);
    }

    public function lister(FiltrerReportingsRequest $request): JsonResponse
    {
        $reportings = $this->reportingService->listerReportings(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => $reportings->getCollection()
                ->map(fn (Reporting $reporting): array => [
                    'uuid' => $reporting->uuid,
                    'date_reporting' => $reporting->date_reporting->toDateString(),
                    'statut_generation' => $reporting->statut_generation,
                    'genere_le' => $reporting->genere_le?->toIso8601String(),
                    'agence' => [
                        'uuid' => $reporting->agence->uuid,
                        'code' => $reporting->agence->code_agence,
                        'nom' => $reporting->agence->nom_agence,
                    ],
                    'indicateurs' => $reporting->indicateurs,
                ]),
            'meta' => [
                'current_page' => $reportings->currentPage(),
                'last_page' => $reportings->lastPage(),
                'per_page' => $reportings->perPage(),
                'total' => $reportings->total(),
            ],
        ]);
    }

    public function consulter(string $uuid): JsonResponse
    {
        $reporting = $this->reportingService->trouverReportingAccessible(
            request()->user(),
            $uuid
        );
        $this->authorize('consulter', $reporting);

        return response()->json(['data' => $reporting]);
    }

    public function generer(GenererReportingRequest $request): JsonResponse
    {
        $reporting = $this->reportingService->genererReporting(
            $request->user(),
            $request->validated()
        );
        $status = $reporting->wasRecentlyCreated ? 201 : 200;

        return response()->json([
            'data' => $reporting,
            'message' => $status === 201
                ? 'Reporting quotidien généré.'
                : 'Reporting quotidien recalculé.',
        ], $status);
    }

    public function transactions(FiltrerReportingsRequest $request): JsonResponse
    {
        $transactions = $this->reportingService->listerTransactions(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => $transactions->getCollection()
                ->map(fn ($transaction): array => [
                    'reference_transaction' => $transaction->reference_transaction,
                    'date_transaction' => $transaction->date_transaction?->toIso8601String(),
                    'type_transaction' => $transaction->type_transaction,
                    'montant' => $transaction->montant,
                    'devise' => $transaction->devise,
                    'agence_id' => $transaction->agence_id,
                ]),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
            ],
        ]);
    }

    public function exporter(FiltrerReportingsRequest $request): StreamedResponse
    {
        $export = $this->reportingService->preparerExport(
            $request->user(),
            $request->validated()
        );
        $dateGeneration = now()->toIso8601String();

        return response()->streamDownload(
            function () use ($export, $dateGeneration): void {
                $sortie = fopen('php://output', 'wb');
                if ($sortie === false) {
                    throw new \RuntimeException('Impossible d’ouvrir le flux CSV.');
                }

                fputcsv($sortie, ['Rapport CRM'], ';', '"', '\\');
                fputcsv($sortie, ['Généré le', $dateGeneration], ';', '"', '\\');
                fputcsv(
                    $sortie,
                    [
                        'Période',
                        $export['indicateurs']['periode']['date_debut'],
                        $export['indicateurs']['periode']['date_fin'],
                    ],
                    ';',
                    '"',
                    '\\'
                );
                fputcsv(
                    $sortie,
                    ['Périmètre', $export['indicateurs']['perimetre']],
                    ';',
                    '"',
                    '\\'
                );
                fputcsv($sortie, [], ';', '"', '\\');
                fputcsv($sortie, ['Indicateur', 'Valeur'], ';', '"', '\\');

                foreach ($export['indicateurs']['indicateurs_generaux'] as $nom => $valeur) {
                    fputcsv($sortie, [$nom, $valeur], ';', '"', '\\');
                }

                foreach ($export['indicateurs']['indicateurs_credits'] as $nom => $valeur) {
                    fputcsv($sortie, [$nom, $valeur], ';', '"', '\\');
                }

                fputcsv($sortie, [], ';', '"', '\\');
                fputcsv(
                    $sortie,
                    ['Type', 'Nombre', 'Montant total', 'Montant moyen'],
                    ';',
                    '"',
                    '\\'
                );
                foreach ($export['indicateurs']['transactions'] as $type => $statistique) {
                    fputcsv(
                        $sortie,
                        [
                            $type,
                            $statistique['nombre'],
                            $statistique['montant_total'],
                            $statistique['montant_moyen'],
                        ],
                        ';',
                        '"',
                        '\\'
                    );
                }

                fputcsv($sortie, [], ';', '"', '\\');
                fputcsv(
                    $sortie,
                    [
                        'Référence',
                        'Date',
                        'Type',
                        'Montant',
                        'Devise',
                        'Agence ID',
                    ],
                    ';',
                    '"',
                    '\\'
                );
                foreach ($export['transactions']->cursor() as $transaction) {
                    fputcsv(
                        $sortie,
                        [
                            $transaction->reference_transaction,
                            $transaction->date_transaction,
                            $transaction->type_transaction,
                            $transaction->montant,
                            $transaction->devise,
                            $transaction->agence_id,
                        ],
                        ';',
                        '"',
                        '\\'
                    );
                }

                fclose($sortie);
            },
            'reporting_'.now()->format('Ymd_His').'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
