<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reporting;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ReportingPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->estAutoriseAConsulter($user)
            && (
                $this->accesAgence->peutAccederAuReseau($user)
                || $user->agence_id !== null
            );
    }

    public function consulter(User $user, Reporting $reporting): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $reporting->agence_id);

        return $acces === true && $this->estAutoriseAConsulter($user);
    }

    public function exporterTout(User $user): bool
    {
        return $user->role === UserRole::DIRECTION
            || (
                $user->role === UserRole::CHEF_AGENCE
                && $user->agence_id !== null
            );
    }

    public function exporter(User $user, Reporting $reporting): bool|Response
    {
        $acces = $this->verifierAccesAgence($user, $reporting->agence_id);

        return $acces === true
            && (
                $user->role === UserRole::DIRECTION
                || $user->role === UserRole::CHEF_AGENCE
            );
    }

    private function estAutoriseAConsulter(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMINISTRATEUR,
            UserRole::DIRECTION,
            UserRole::CHEF_AGENCE,
        ], true);
    }
}
