<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ApprouverCreditRequest;
use App\Http\Requests\Api\V1\CreerCreditRequest;
use App\Http\Requests\Api\V1\EnregistrerRemboursementRequest;
use App\Http\Requests\Api\V1\ListerCreditsRequest;
use App\Http\Requests\Api\V1\ModifierCreditRequest;
use App\Http\Requests\Api\V1\RejeterCreditRequest;
use App\Http\Resources\Api\v1\CreditResource;
use App\Http\Resources\Api\v1\TransactionResource;
use App\Models\Compte;
use App\Models\Credit;
use App\Services\GestionCreditsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CreditController extends Controller
{
    public function __construct(
        protected GestionCreditsService $gestionCredits
    ) {}

    public function lister(
        ListerCreditsRequest $request
    ): AnonymousResourceCollection {
        return CreditResource::collection(
            $this->gestionCredits->listerCredits(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des crédits.']);
    }

    public function suivi(Request $request): JsonResponse
    {
        $this->authorize('consulterTout', Credit::class);

        return response()->json([
            'data' => $this->gestionCredits->suivi($request->user()),
        ]);
    }

    public function creer(
        CreerCreditRequest $request,
        string $uuid
    ): JsonResponse {
        $utilisateur = $request->user();
        $compte = Compte::query()
            ->accessiblePar($utilisateur)
            ->where('uuid', $uuid)
            ->firstOrFail();
        $this->authorize('preparerDemande', [Credit::class, $compte]);

        $credit = $this->gestionCredits->creerDemande(
            $utilisateur,
            $compte,
            $request->validated()
        );

        return (new CreditResource($credit))
            ->additional(['message' => 'Demande de crédit créée.'])
            ->response()
            ->setStatusCode(201);
    }

    public function consulter(Request $request, string $uuid): CreditResource
    {
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $credit);

        return new CreditResource($credit);
    }

    public function modifier(
        ModifierCreditRequest $request,
        string $uuid
    ): CreditResource {
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('modifierDemande', $credit);

        return new CreditResource(
            $this->gestionCredits->modifierDemande(
                $request->user(),
                $uuid,
                $request->validated()
            )
        );
    }

    public function soumettre(Request $request, string $uuid): CreditResource
    {
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('soumettreDemande', $credit);

        return (new CreditResource(
            $this->gestionCredits->soumettreDemande($request->user(), $uuid)
        ))->additional(['message' => 'Demande transmise au chef d’agence.']);
    }

    public function approuver(
        ApprouverCreditRequest $request,
        string $uuid
    ): CreditResource {
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('approuver', $credit);

        $montantAccorde = $request->validated('montant_accorde');

        return (new CreditResource(
            $this->gestionCredits->approuverDemande(
                $request->user(),
                $uuid,
                $montantAccorde === null ? null : (string) $montantAccorde
            )
        ))->additional(['message' => 'Crédit approuvé et déboursé.']);
    }

    public function rejeter(
        RejeterCreditRequest $request,
        string $uuid
    ): CreditResource {
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('rejeter', $credit);

        return (new CreditResource(
            $this->gestionCredits->rejeterDemande(
                $request->user(),
                $uuid,
                $request->validated('motif_rejet')
            )
        ))->additional(['message' => 'Demande de crédit rejetée.']);
    }

    public function rembourser(
        EnregistrerRemboursementRequest $request,
        string $uuid
    ): JsonResponse {
        $utilisateur = $request->user();
        $credit = $this->gestionCredits->trouverCreditAccessibleParUuid(
            $utilisateur,
            $uuid
        );
        $this->authorize('enregistrerRemboursement', $credit);

        $resultat = $this->gestionCredits->enregistrerRemboursement(
            $utilisateur,
            $uuid,
            $request->validated('compte_uuid'),
            (string) $request->validated('montant'),
            $request->validated('motif'),
            $request->validated('cle_idempotence')
        );

        return response()->json([
            'data' => [
                'transaction' => (new TransactionResource(
                    $resultat['transaction']
                ))->resolve($request),
                'echeances' => $resultat['echeances'],
            ],
            'message' => 'Remboursement enregistré.',
        ], $resultat['rejoue'] ? 200 : 201);
    }
}
