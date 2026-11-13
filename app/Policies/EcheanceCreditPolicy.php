<?php

namespace App\Policies;

use App\Models\EcheanceCredit;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class EcheanceCreditPolicy extends PolitiqueAgence
{
    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulter(
        User $user,
        EcheanceCredit $echeance
    ): bool|Response {
        return $this->verifierAccesAgence(
            $user,
            $echeance->credit?->compte?->agence_id
        );
    }
}
