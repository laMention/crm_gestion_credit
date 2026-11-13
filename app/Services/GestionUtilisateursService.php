<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GestionUtilisateursService
{
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

    // Trouve un utilisateur accessible par son UUID
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
    public function creer(array $donnees): User
    {
        $utilisateur = User::query()->create($donnees);

        return $this->chargerAgence($utilisateur);
    }

    /**
     * @param  array<string, mixed>  $donnees
     * modifier un utilisateur
     */
    public function modifier(User $utilisateur, array $donnees): User
    {
        $utilisateur->fill($donnees)->save();

        return $this->chargerAgence($utilisateur);
    }

    // Activer un utilisateur
    public function activer(User $utilisateur): User
    {
        $utilisateur->forceFill(['actif' => true])->save();

        return $this->chargerAgence($utilisateur);
    }

    // Désactiver un utilisateur
    public function desactiver(User $utilisateur): User
    {
        $utilisateur->forceFill(['actif' => false])->save();
        $utilisateur->tokens()->delete();

        return $this->chargerAgence($utilisateur);
    }

    // Charger l'agence associée à un utilisateur
    private function chargerAgence(User $utilisateur): User
    {
        return $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');
    }
}
