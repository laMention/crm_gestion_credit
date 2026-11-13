<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListerComptesRequest;
use App\Http\Requests\Api\V1\OuvrirCompteRequest;
use App\Http\Requests\Api\V1\ReleveCompteRequest;
use App\Http\Resources\Api\v1\CompteResource;
use App\Services\GestionClientsComptesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
// use App\Models\Compte;

class CompteController extends Controller
{
    protected GestionClientsComptesService $gestionClientsComptes;

    public function __construct(GestionClientsComptesService $gestionClientsComptes)
    {
        $this->gestionClientsComptes = $gestionClientsComptes;
    }

    public function lister(
        ListerComptesRequest $request
    ): AnonymousResourceCollection {
        return CompteResource::collection(
            $this->gestionClientsComptes->listerComptes(
                $request->user(),
                $request->validated()
            )
        )->additional(['message' => 'Liste des comptes.']);
    }

    public function ouvrir(
        OuvrirCompteRequest $request,
        string $uuid,

    ): JsonResponse {
        $client = $this->gestionClientsComptes->trouverClientAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('ouvrir', [Compte::class, $client]);

        $compte = $this->gestionClientsComptes->ouvrirCompte(
            $request->user(),
            $client,
            $request->validated()
        );

        return (new CompteResource($compte))
            ->additional(['message' => 'Compte ouvert.'])
            ->response()
            ->setStatusCode(201);
    }

    public function consulter(
        Request $request,
        string $uuid,

    ): CompteResource {
        $compte = $this->gestionClientsComptes->trouverCompteAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $compte);

        return new CompteResource($compte);
    }

    // Generer un releve de compte pour un client donné
    public function releve(
        ReleveCompteRequest $request,
        string $uuid,

    ): JsonResponse {
        $compte = $this->gestionClientsComptes->trouverCompteAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $compte);

        return response()->json([
            'data' => $this->gestionClientsComptes->genererReleve(
                $compte,
                $request->validated('date_debut'),
                $request->validated('date_fin'),
                $request->validated('operations_par_page', 15)
            ),
        ]);
    }
}
