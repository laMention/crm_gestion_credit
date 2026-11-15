<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GestionUtilisateursService
{
    public function __construct(
        private readonly AuditLogService $journalAudit
    ) {}

    /**
     * @param array{
     *     recherche?: string,
     *     role?: string,
     *     agence_id?: int,
     *     actif?: bool,
     *     per_page?: int
     * } $filtres
     */
    public function lister(User $demandeur, array $filtres): LengthAwarePaginator
    {
        return User::query()
            ->accessiblePar($demandeur)
            ->with('agence:id,uuid,code_agence,nom_agence,ville_agence')
            ->when(
                isset($filtres['recherche']) && $filtres['recherche'] !== '',
                function (Builder $requete) use ($filtres): void {
                    $terme = '%'.$filtres['recherche'].'%';
                    $requete->where(function (Builder $requeteRecherche) use ($terme): void {
                        $requeteRecherche
                            ->where('nom', 'like', $terme)
                            ->orWhere('prenom', 'like', $terme)
                            ->orWhere('email', 'like', $terme)
                            ->orWhere('telephone', 'like', $terme);
                    });
                }
            )
            ->when(
                isset($filtres['role']),
                fn (Builder $requete): Builder => $requete->where('role', $filtres['role'])
            )
            ->when(
                isset($filtres['agence_id']),
                fn (Builder $requete): Builder => $requete->where(
                    'agence_id',
                    $filtres['agence_id']
                )
            )
            ->when(
                array_key_exists('actif', $filtres),
                fn (Builder $requete): Builder => $requete->where('actif', $filtres['actif'])
            )
            ->orderBy('nom')
            ->orderBy('prenom')
            ->orderBy('id')
            ->paginate($filtres['per_page'] ?? 15);
    }

    public function trouverAccessibleParUuid(User $demandeur, string $uuid): User
    {
        return User::query()
            ->accessiblePar($demandeur)
            ->with('agence:id,uuid,code_agence,nom_agence,ville_agence')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    public function creer(User $acteur, array $donnees): User
    {
        return DB::transaction(function () use ($acteur, $donnees): User {
            $utilisateur = User::query()->create($donnees);
            $this->journalAudit->enregistrer(
                $acteur,
                'utilisateur.cree',
                'utilisateurs',
                'utilisateur',
                $utilisateur->id,
                [],
                [
                    'role' => $utilisateur->role->value,
                    'agence_id' => $utilisateur->agence_id,
                    'actif' => $utilisateur->actif,
                ],
                agenceId: $acteur->agence_id
            );

            return $this->chargerAgence($utilisateur);
        });
    }

    /**
     * @param  array<string, mixed>  $donnees
     *                                         modifier un utilisateur
     */
    public function modifier(
        User $acteur,
        User $utilisateur,
        array $donnees
    ): User {
        return DB::transaction(function () use ($acteur, $utilisateur, $donnees): User {
            $anciennesValeurs = [
                'role' => $utilisateur->role->value,
                'agence_id' => $utilisateur->agence_id,
                'actif' => $utilisateur->actif,
            ];
            $utilisateur->fill($donnees)->save();
            $this->journalAudit->enregistrer(
                $acteur,
                'utilisateur.modifie',
                'utilisateurs',
                'utilisateur',
                $utilisateur->id,
                $anciennesValeurs,
                [
                    'role' => $utilisateur->role->value,
                    'agence_id' => $utilisateur->agence_id,
                    'actif' => $utilisateur->actif,
                ],
                agenceId: $acteur->agence_id
            );

            return $this->chargerAgence($utilisateur);
        });
    }

    public function activer(User $acteur, User $utilisateur): User
    {
        return DB::transaction(function () use ($acteur, $utilisateur): User {
            $ancienEtat = $utilisateur->actif;
            $utilisateur->forceFill(['actif' => true])->save();
            $this->journalAudit->enregistrer(
                $acteur,
                'utilisateur.active',
                'utilisateurs',
                'utilisateur',
                $utilisateur->id,
                ['actif' => $ancienEtat],
                ['actif' => true],
                agenceId: $acteur->agence_id
            );

            return $this->chargerAgence($utilisateur);
        });
    }

    public function desactiver(User $acteur, User $utilisateur): User
    {
        return DB::transaction(function () use ($acteur, $utilisateur): User {
            $ancienEtat = $utilisateur->actif;
            $utilisateur->forceFill(['actif' => false])->save();
            $utilisateur->tokens()->delete();
            $this->journalAudit->enregistrer(
                $acteur,
                'utilisateur.desactive',
                'utilisateurs',
                'utilisateur',
                $utilisateur->id,
                ['actif' => $ancienEtat],
                ['actif' => false],
                agenceId: $acteur->agence_id
            );

            return $this->chargerAgence($utilisateur);
        });
    }

    private function chargerAgence(User $utilisateur): User
    {
        return $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');
    }
}
