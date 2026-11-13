<?php

namespace App\Services\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AccesAgenceService
{
    public function peutAccederAgence(
        User $user,
        ?int $agenceId
    ): bool {
        if ($this->peutAccederAuReseau($user)) {
            return true;
        }

        return $agenceId !== null && $user->agence_id === $agenceId;
    }

    public function peutAccederAuReseau(User $user): bool
    {
        return $user->role->roleEchelleDuReseau();
    }

    public function estChefAgence(User $user): bool
    {
        return $user->role === UserRole::CHEF_AGENCE;
    }

    /**
     * Restrict a query before its records are retrieved.
     */
    public function filtrerParAgence(
        User $user,
        Builder $requete,
        string $colonneAgence = 'agence_id',
        ?string $relation = null
    ): Builder {
        if ($this->peutAccederAuReseau($user)) {
            return $requete;
        }

        if ($user->agence_id === null) {
            return $requete->whereRaw('1 = 0');
        }

        $filtreAgence = fn (Builder $requeteAgence): Builder => $requeteAgence
            ->where($colonneAgence, $user->agence_id);

        if ($relation !== null) {
            return $requete->whereHas($relation, $filtreAgence);
        }

        return $filtreAgence($requete);
    }
}
