<?php

namespace App\Policies;

use App\Models\User;
use App\Services\Authorization\AccesAgenceService;
use Illuminate\Auth\Access\Response;

/**
 * Apply the agency boundary without granting a role any resource permission.
 */
abstract class PolitiqueAgence
{
    public function __construct(
        protected AccesAgenceService $accesAgence
    ) {}

    protected function verifierAccesAgence(
        User $user,
        ?int $agenceId
    ): bool|Response {
        if ($this->accesAgence->peutAccederAgence($user, $agenceId)) {
            return true;
        }

        return Response::denyAsNotFound();
    }
}
