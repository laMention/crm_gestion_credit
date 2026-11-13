<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ClientPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulter(User $user, Client $client): bool|Response
    {
        return $this->verifierAccesAgence($user, $client->agence_id);
    }

    public function creer(User $user): bool
    {
        return $user->role === UserRole::CONSEILLER
            && $user->agence_id !== null;
    }

    public function modifier(User $user, Client $client): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $client->agence_id);

        return $acces === true
            && $user->role === UserRole::CONSEILLER;
    }

    public function bloquer(User $user, Client $client): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $client->agence_id);

        return $acces === true
            && $user->role === UserRole::CHEF_AGENCE;
    }

    public function debloquer(User $user, Client $client): bool|Response
    {
        return $this->bloquer($user, $client);
    }
}
