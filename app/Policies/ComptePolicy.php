<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Compte;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ComptePolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulter(User $user, Compte $compte): bool|Response
    {
        return $this->verifierAccesAgence($user, $compte->agence_id);
    }

    public function ouvrir(User $user, Client $client): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $client->agence_id);

        return $acces === true
            && $user->role === UserRole::CONSEILLER;
    }

    public function effectuerDepot(
        User $user,
        Compte $compte
    ): bool|Response {
        return $this->autoriserOperationCaissier($user, $compte);
    }

    public function effectuerRetrait(
        User $user,
        Compte $compte
    ): bool|Response {
        return $this->autoriserOperationCaissier($user, $compte);
    }

    public function effectuerVirementAssiste(
        User $user,
        Compte $compteSource,
        Compte $compteDestinataire
    ): bool|Response {
        $accesSource = $this->verifierAccesAgence(
            $user,
            $compteSource->agence_id
        );
        $accesDestinataire = $this->verifierAccesAgence(
            $user,
            $compteDestinataire->agence_id
        );

        return $accesSource === true
            && $accesDestinataire === true
            && $user->role === UserRole::CAISSIER;
    }

    public function bloquer(User $user, Compte $compte): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $compte->agence_id);

        return $acces === true
            && $user->role === UserRole::CHEF_AGENCE;
    }

    public function debloquer(User $user, Compte $compte): bool|Response
    {
        return $this->bloquer($user, $compte);
    }

    public function autoriserCloture(User $user, Compte $compte): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $compte->agence_id);

        return $acces === true
            && $user->role === UserRole::CHEF_AGENCE;
    }

    private function autoriserOperationCaissier(
        User $user,
        Compte $compte
    ): bool|Response {
        $acces = $this->verifierAccesAgence($user, $compte->agence_id);

        return $acces === true
            && $user->role === UserRole::CAISSIER;
    }
}
