<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy extends PolitiqueAgence
{
    const LIMITE_RETRAIT = 2000000;

    public function consulterTout(User $user): bool
    {
        return $this->accesAgence->peutAccederAuReseau($user)
            || $user->agence_id !== null;
    }

    public function consulterRetraitsEnAttente(User $user): bool
    {
        return $this->accesAgence->estChefAgence($user)
            && $user->agence_id !== null;
    }

    public function consulter(User $user, Transaction $transaction): bool|Response
    {
        return $this->verifierAccesAgence($user, $transaction->agence_id);
    }

    public function validerRetrait(
        User $user,
        Transaction $transaction
    ): bool|Response {
        $acces = $this->verifierAccesAgence($user, $transaction->agence_id);

        return $acces === true
            && $this->accesAgence->estChefAgence($user)
            && $transaction->type_transaction === 'retrait'
            && $transaction->statut_transaction === 'en_attente'
            && $transaction->devise === 'XOF'
            && $transaction->montant > self::LIMITE_RETRAIT
            && $transaction->user_id !== $user->id;
    }

    public function soumettreCorrection(
        User $user,
        Transaction $transaction
    ): bool|Response {
        $acces = $this->verifierAccesAgence($user, $transaction->agence_id);

        return $acces === true && $this->estCaissierAgence($user);
    }

    public function validerCorrection(
        User $user,
        Transaction $transaction
    ): bool|Response {
        $acces = $this->verifierAccesAgence($user, $transaction->agence_id);

        return $acces === true
            && $this->accesAgence->estChefAgence($user)
            && $transaction->user_id !== $user->id;
    }
}
