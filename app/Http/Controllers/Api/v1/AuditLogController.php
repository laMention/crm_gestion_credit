<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListerAuditLogsRequest;
use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\JsonResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly AuditLogService $journalAudit
    ) {}

    public function lister(ListerAuditLogsRequest $request): JsonResponse
    {
        $journaux = $this->journalAudit->lister(
            $request->user(),
            $request->validated()
        );

        return response()->json([
            'data' => $journaux->getCollection()->map(
                fn (AuditLog $journal): array => $this->formaterJournal($journal)
            ),
            'meta' => [
                'current_page' => $journaux->currentPage(),
                'last_page' => $journaux->lastPage(),
                'per_page' => $journaux->perPage(),
                'total' => $journaux->total(),
            ],
        ]);
    }

    public function consulter(string $uuid): JsonResponse
    {
        $journal = $this->journalAudit->trouverAccessible(request()->user(), $uuid);
        $this->authorize('consulter', $journal);

        return response()->json(['data' => $this->formaterJournal($journal)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formaterJournal(AuditLog $journal): array
    {
        return [
            'uuid' => $journal->uuid,
            'date_heure' => $journal->created_at?->toIso8601String(),
            'utilisateur' => $journal->user ? [
                'uuid' => $journal->user->uuid,
                'nom' => $journal->user->nom,
                'prenom' => $journal->user->prenom,
            ] : null,
            'role_utilisateur' => $journal->role_utilisateur,
            'agence_id' => $journal->agence_id,
            'module' => $journal->module,
            'action' => $journal->action,
            'type_ressource' => $journal->type_ressource,
            'enregistrement_id' => $journal->enregistrement_id,
            'resultat' => $journal->resultat,
            'anciennes_valeurs' => $this->journalAudit->retirerDonneesSensibles(
                $journal->anciennes_valeurs ?? []
            ),
            'nouvelles_valeurs' => $this->journalAudit->retirerDonneesSensibles(
                $journal->nouvelles_valeurs ?? []
            ),
            'adresse_ip' => $journal->adresse_ip,
            'user_agent' => $journal->user_agent,
            'description' => $journal->description,
        ];
    }
}
