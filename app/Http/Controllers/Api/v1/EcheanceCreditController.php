<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListerEcheancesRequest;
use App\Http\Resources\Api\v1\EcheanceCreditResource;
use App\Services\GestionCreditsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EcheanceCreditController extends Controller
{
    public function __construct(
        protected GestionCreditsService $gestionCredits
    ) {}

    public function lister(
        ListerEcheancesRequest $request
    ): AnonymousResourceCollection {
        return EcheanceCreditResource::collection(
            $this->gestionCredits->listerEcheances(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des échéances.']);
    }

    public function consulter(Request $request, string $uuid): EcheanceCreditResource
    {
        $echeance = $this->gestionCredits->trouverEcheanceAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $echeance);

        return new EcheanceCreditResource($echeance);
    }
}
