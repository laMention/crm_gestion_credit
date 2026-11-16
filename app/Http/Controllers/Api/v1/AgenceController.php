<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreerAgenceRequest;
use App\Http\Requests\Api\V1\ListerAgencesRequest;
use App\Http\Requests\Api\V1\ModifierAgenceRequest;
use App\Http\Resources\Api\v1\AgenceResource;
use App\Models\Agence;
use App\Services\GestionAgencesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AgenceController extends Controller
{
    public function __construct(
        private readonly GestionAgencesService $gestionAgences
    ) {}

    public function lister(
        ListerAgencesRequest $request
    ): AnonymousResourceCollection {
        return AgenceResource::collection(
            $this->gestionAgences->lister(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des agences.']);
    }

    public function consulter(Request $request, Agence $agence): AgenceResource
    {
        $this->authorize('consulter', $agence);

        return new AgenceResource($agence);
    }

    public function creer(CreerAgenceRequest $request): JsonResponse
    {
        $agence = $this->gestionAgences->creer(
            $request->user(),
            $request->validated()
        );

        return (new AgenceResource($agence))
            ->additional(['message' => 'Agence créée.'])
            ->response()
            ->setStatusCode(201);
    }

    public function modifier(
        ModifierAgenceRequest $request,
        Agence $agence
    ): AgenceResource {
        $this->authorize('modifier', $agence);

        $agence = $this->gestionAgences->modifier(
            $request->user(),
            $agence,
            $request->validated()
        );

        return (new AgenceResource($agence))
            ->additional(['message' => 'Agence modifiée.']);
    }
}
