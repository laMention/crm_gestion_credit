<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreerClientRequest;
use App\Http\Requests\Api\V1\ListerClientsRequest;
use App\Http\Requests\Api\V1\ModifierClientRequest;
use App\Http\Resources\Api\v1\ClientResource;
use App\Services\GestionClientsComptesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ClientController extends Controller
{
    protected GestionClientsComptesService $gestionClientsComptes;

    public function __construct(GestionClientsComptesService $gestionClientsComptes)
    {
        $this->gestionClientsComptes = $gestionClientsComptes;
    }

    public function lister(
        ListerClientsRequest $request,
    ): AnonymousResourceCollection {
        return ClientResource::collection(
            $this->gestionClientsComptes->listerClients(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des clients.']);
    }

    public function creer(
        CreerClientRequest $request,
    ): JsonResponse {
        $client = $this->gestionClientsComptes->creerClient(
            $request->user(),
            $request->validated()
        );

        return (new ClientResource($client))
            ->additional(['message' => 'Client créé.'])
            ->response()
            ->setStatusCode(201);
    }

    public function consulter(
        Request $request,
        string $uuid,
    ): ClientResource {
        $client = $this->gestionClientsComptes->trouverClientAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $client);

        return new ClientResource($client);
    }

    public function modifier(
        ModifierClientRequest $request,
        string $uuid,
    ): ClientResource {
        $client = $this->gestionClientsComptes->trouverClientAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('modifier', $client);

        $client = $this->gestionClientsComptes->modifierClient(
            $client,
            $request->validated()
        );

        return (new ClientResource($client))
            ->additional(['message' => 'Client modifié.']);
    }
}
