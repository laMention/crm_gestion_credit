<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AgencePolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulter(User $user, Agence $agence): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $agence->id);

        return $acces === true ? true : $acces;
    }

    public function creer(User $user): bool
    {
        return $user->role === UserRole::ADMINISTRATEUR;
    }

    public function modifier(User $user, Agence $agence): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $agence->id);

        if ($acces !== true) {
            return $acces;
        }

        return $user->role === UserRole::ADMINISTRATEUR;
    }
}
