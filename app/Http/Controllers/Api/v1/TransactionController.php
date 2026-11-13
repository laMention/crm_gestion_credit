<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\EffectuerDepotRequest;
use App\Http\Requests\Api\V1\EffectuerRetraitRequest;
use App\Http\Requests\Api\V1\EffectuerVirementRequest;
use App\Http\Requests\Api\V1\ListerTransactionsRequest;
use App\Http\Requests\Api\V1\TraiterRetraitRequest;
use App\Http\Resources\Api\v1\TransactionResource;
use App\Models\Transaction;
use App\Services\OperationsFinancieresService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    public function __construct(
        protected OperationsFinancieresService $operationsFinancieres
    ) {}

    public function deposer(
        EffectuerDepotRequest $request,
        string $uuid
    ): JsonResponse {
        $utilisateur = $request->user();
        $compte = $this->operationsFinancieres->trouverCompteAccessibleParUuid(
            $utilisateur,
            $uuid
        );
        $this->authorize('effectuerDepot', $compte);

        $resultat = $this->operationsFinancieres->deposer(
            $utilisateur,
            $compte,
            $request->validated('montant'),
            $request->validated('motif'),
            $request->validated('cle_idempotence')
        );

        return (new TransactionResource($resultat['transaction']))
            ->additional(['message' => 'Dépôt effectué.'])
            ->response()
            ->setStatusCode($resultat['rejouee'] ? 200 : 201);
    }

    public function retirer(
        EffectuerRetraitRequest $request,
        string $uuid
    ): JsonResponse {
        $utilisateur = $request->user();
        $compte = $this->operationsFinancieres->trouverCompteAccessibleParUuid(
            $utilisateur,
            $uuid
        );
        $this->authorize('effectuerRetrait', $compte);

        $resultat = $this->operationsFinancieres->retirer(
            $utilisateur,
            $compte,
            $request->validated('montant'),
            $request->validated('motif'),
            $request->validated('cle_idempotence')
        );
        $transaction = $resultat['transaction'];
        $message = $transaction->statut_transaction === 'en_attente'
            ? 'Retrait soumis à validation.'
            : 'Retrait effectué.';
        $status = $resultat['rejouee']
            ? 200
            : ($transaction->statut_transaction === 'en_attente' ? 202 : 201);

        return (new TransactionResource($transaction))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }

    public function virer(
        EffectuerVirementRequest $request,
        string $uuid
    ): JsonResponse {
        $utilisateur = $request->user();
        $compteSource = $this->operationsFinancieres->trouverCompteAccessibleParUuid(
            $utilisateur,
            $uuid
        );
        $compteDestinataire = $this->operationsFinancieres->trouverCompteAccessibleParUuid(
            $utilisateur,
            $request->validated('compte_destinataire_uuid')
        );
        $this->authorize('effectuerVirementAssiste', [
            $compteSource,
            $compteDestinataire,
        ]);

        $resultat = $this->operationsFinancieres->virer(
            $utilisateur,
            $compteSource,
            $compteDestinataire,
            $request->validated('montant'),
            $request->validated('motif'),
            $request->validated('cle_idempotence')
        );

        return (new TransactionResource($resultat['transaction']))
            ->additional(['message' => 'Virement effectué.'])
            ->response()
            ->setStatusCode($resultat['rejouee'] ? 200 : 201);
    }

    public function lister(
        ListerTransactionsRequest $request
    ): AnonymousResourceCollection {
        return TransactionResource::collection(
            $this->operationsFinancieres->listerTransactions(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des transactions.']);
    }

    public function consulter(Request $request, string $uuid): TransactionResource
    {
        $transaction = $this->operationsFinancieres
            ->trouverTransactionAccessibleParUuid($request->user(), $uuid);
        $this->authorize('consulter', $transaction);

        return new TransactionResource($transaction);
    }

    public function listerRetraitsEnAttente(
        Request $request
    ): AnonymousResourceCollection {
        $this->authorize('consulterRetraitsEnAttente', Transaction::class);

        return TransactionResource::collection(
            $this->operationsFinancieres->listerTransactions(
                $request->user(),
                [],
                true
            )
        )->additional(['message' => 'Retraits en attente de validation.']);
    }

    public function approuverRetrait(
        TraiterRetraitRequest $request,
        string $uuid
    ): TransactionResource {
        $transaction = $this->operationsFinancieres->approuverRetrait(
            $request->user(),
            $uuid
        );

        return (new TransactionResource($transaction))
            ->additional(['message' => 'Retrait approuvé et effectué.']);
    }

    public function rejeterRetrait(
        TraiterRetraitRequest $request,
        string $uuid
    ): TransactionResource {
        $transaction = $this->operationsFinancieres->rejeterRetrait(
            $request->user(),
            $uuid,
            $request->validated('motif')
        );

        return (new TransactionResource($transaction))
            ->additional(['message' => 'Retrait rejeté.']);
    }
}
