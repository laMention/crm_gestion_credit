<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || (
                $user->role === UserRole::CHEF_AGENCE
                && $user->agence_id !== null
            );
    }

    public function consulter(User $user, User $utilisateur): bool
    {
        $acces = $this->verifierAccesAgence($user, $utilisateur->agence_id);

        return $acces === true
            && (
                $this->accesAgence->peutAccederAuReseau($user)
                || $user->role === UserRole::CHEF_AGENCE
            );
    }

    public function creer(User $user): bool
    {
        return $user->role === UserRole::ADMINISTRATEUR;
    }

    public function modifier(User $user, User $utilisateur): bool
    {
        $acces = $this->verifierAccesAgence($user, $utilisateur->agence_id);

        return $acces === true
            && $user->role === UserRole::ADMINISTRATEUR;
    }

    public function activer(User $user, User $utilisateur): bool
    {
        return $this->modifier($user, $utilisateur);
    }

    public function desactiver(User $user, User $utilisateur): bool
    {
        return $this->modifier($user, $utilisateur);
    }
}
