<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AuditLogService
{
    /**
     * @param  array<string, mixed>  $filtres
     */
    public function lister(
        User $utilisateur,
        array $filtres
    ): LengthAwarePaginator {
        return AuditLog::query()
            ->accessiblePar($utilisateur)
            ->with('user:id,uuid,nom,prenom,role,agence_id')
            ->when(
                isset($filtres['user_uuid']),
                fn (Builder $journaux): Builder => $journaux->whereHas(
                    'user',
                    fn (Builder $users): Builder => $users->where(
                        'uuid',
                        $filtres['user_uuid']
                    )
                )
            )
            ->when(
                isset($filtres['role']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'role_utilisateur',
                    $filtres['role']
                )
            )
            ->when(
                isset($filtres['agence_id']) && $utilisateur->role->roleEchelleDuReseau(),
                fn (Builder $journaux): Builder => $journaux->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->when(
                isset($filtres['module']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'module',
                    $filtres['module']
                )
            )
            ->when(
                isset($filtres['action']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'action',
                    'like',
                    '%'.$filtres['action'].'%'
                )
            )
            ->when(
                isset($filtres['type_ressource']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'type_ressource',
                    $filtres['type_ressource']
                )
            )
            ->when(
                isset($filtres['enregistrement_id']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'enregistrement_id',
                    $filtres['enregistrement_id']
                )
            )
            ->when(
                isset($filtres['resultat']),
                fn (Builder $journaux): Builder => $journaux->where(
                    'resultat',
                    $filtres['resultat']
                )
            )
            ->when(
                isset($filtres['date_debut']),
                fn (Builder $journaux): Builder => $journaux->whereDate(
                    'created_at',
                    '>=',
                    $filtres['date_debut']
                )
            )
            ->when(
                isset($filtres['date_fin']),
                fn (Builder $journaux): Builder => $journaux->whereDate(
                    'created_at',
                    '<=',
                    $filtres['date_fin']
                )
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filtres['per_page'] ?? 25);
    }

    public function trouverAccessible(User $utilisateur, string $uuid): AuditLog
    {
        return AuditLog::query()
            ->accessiblePar($utilisateur)
            ->with('user:id,uuid,nom,prenom,role,agence_id')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $anciennesValeurs
     * @param  array<string, mixed>  $nouvellesValeurs
     */
    public function enregistrer(
        ?User $utilisateur,
        string $action,
        string $module,
        string $typeRessource,
        ?int $enregistrementId = null,
        array $anciennesValeurs = [],
        array $nouvellesValeurs = [],
        string $resultat = 'succes',
        ?int $agenceId = null,
        ?string $description = null
    ): AuditLog {
        $requete = app()->bound('request') ? app('request') : null;
        $requete = $requete instanceof Request ? $requete : null;

        return AuditLog::query()->create([
            'user_id' => $utilisateur?->id,
            'agence_id' => $agenceId ?? $utilisateur?->agence_id,
            'role_utilisateur' => $utilisateur?->role?->value,
            'action' => $action,
            'module' => $module,
            'type_ressource' => $typeRessource,
            'resultat' => $resultat,
            'table_concernee' => $this->tablePourModule($module),
            'enregistrement_id' => $enregistrementId,
            'anciennes_valeurs' => $this->retirerDonneesSensibles($anciennesValeurs),
            'nouvelles_valeurs' => $this->retirerDonneesSensibles($nouvellesValeurs),
            'adresse_ip' => $requete?->ip(),
            'user_agent' => $requete?->userAgent(),
            'description' => $description,
        ]);
    }

    /**
     * @param  array<string, mixed>  $valeurs
     * @return array<string, mixed>
     */
    public function retirerDonneesSensibles(array $valeurs): array
    {
        $clesInterdites = [
            'password',
            'password_confirmation',
            'remember_token',
            'token',
            'access_token',
            'secret',
            'api_key',
            'authorization',
        ];

        foreach ($valeurs as $cle => $valeur) {
            if (in_array(strtolower((string) $cle), $clesInterdites, true)) {
                unset($valeurs[$cle]);

                continue;
            }

            if (is_array($valeur)) {
                $valeurs[$cle] = $this->retirerDonneesSensibles($valeur);
            }
        }

        return $valeurs;
    }

    private function tablePourModule(string $module): string
    {
        return match ($module) {
            'authentification' => 'users',
            'utilisateurs' => 'users',
            'agences' => 'agences',
            'clients' => 'clients',
            'comptes' => 'comptes',
            'transactions' => 'transactions',
            'credits' => 'credits',
            'reportings' => 'reportings',
            default => 'audit_logs',
        };
    }
}
