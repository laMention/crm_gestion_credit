<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CreditPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulter(User $user, Credit $credit): bool|Response
    {
        return $this->verifierAccesAgence(
            $user,
            $credit->compte?->agence_id
        );
    }

    public function preparerDemande(User $user, Compte $compte): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $compte->agence_id);

        return $acces === true
            && $user->role === UserRole::CONSEILLER;
    }

    public function modifierDemande(User $user, Credit $credit): bool|Response
    {
        $acces = $this->verifierAccesAgence(
            $user,
            $credit->compte?->agence_id
        );

        return $acces === true
            && $user->role === UserRole::CONSEILLER
            && $credit->conseiller_client_id === $user->id
            && $credit->statut === 'en_etude';
    }

    public function soumettreDemande(User $user, Credit $credit): bool|Response
    {
        $acces = $this->verifierAccesAgence(
            $user,
            $credit->compte?->agence_id
        );

        return $acces === true
            && $user->role === UserRole::CONSEILLER
            && $credit->conseiller_client_id === $user->id
            && in_array($credit->statut, ['en_attente', 'en_etude'], true);
    }

    public function approuver(User $user, Credit $credit): bool|Response
    {
        return $this->autoriserDecision($user, $credit);
    }

    public function rejeter(User $user, Credit $credit): bool|Response
    {
        return $this->autoriserDecision($user, $credit);
    }

    public function enregistrerRemboursement(
        User $user,
        Credit $credit
    ): bool|Response {
        $acces = $this->verifierAccesAgence(
            $user,
            $credit->compte?->agence_id
        );

        return $acces === true && $user->role === UserRole::CAISSIER;
    }

    private function autoriserDecision(
        User $user,
        Credit $credit
    ): bool|Response {
        $acces = $this->verifierAccesAgence(
            $user,
            $credit->compte?->agence_id
        );

        return $acces === true
            && $this->accesAgence->estChefAgence($user)
            && $credit->conseiller_client_id !== $user->id;
    }
}
