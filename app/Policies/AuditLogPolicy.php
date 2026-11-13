<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AuditLogPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return in_array($user->role, [
            UserRole::ADMINISTRATEUR,
            UserRole::DIRECTION,
            UserRole::CHEF_AGENCE,
        ], true) && (
            $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null
        );
    }

    public function consulter(User $user, AuditLog $journal): bool|Response
    {
        $agenceId = $journal->user?->agence_id;
        $acces = $this->verifierAccesAgence($user, $agenceId);

        return $acces === true
            && in_array($user->role, [
                UserRole::ADMINISTRATEUR,
                UserRole::DIRECTION,
                UserRole::CHEF_AGENCE,
            ], true);
    }
}
