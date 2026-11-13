<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreerUtilisateurRequest;
use App\Http\Requests\Api\V1\ListerUtilisateursRequest;
use App\Http\Requests\Api\V1\ModifierUtilisateurRequest;
use App\Http\Resources\Api\v1\UtilisateurResource;
use App\Services\Authorization\CatalogueRolesPermissionsService;
use App\Services\GestionUtilisateursService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UtilisateurController extends Controller
{
    protected GestionUtilisateursService $gestionUtilisateurs;

    public function __construct(GestionUtilisateursService $gestionUtilisateurs)
    {
        $this->gestionUtilisateurs = $gestionUtilisateurs;
    }

    public function lister(
        ListerUtilisateursRequest $request
    ): AnonymousResourceCollection {
        $utilisateurs = $this->gestionUtilisateurs->lister(
            $request->user(),
            $request->validated()
        );

        return UtilisateurResource::collection($utilisateurs)
            ->additional(['message' => 'Liste des utilisateurs.']);
    }

    public function consulter(
        Request $request,
        string $uuid
    ): UtilisateurResource {
        $utilisateur = $this->gestionUtilisateurs->trouverAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $utilisateur);

        return new UtilisateurResource($utilisateur);
    }

    public function creer(
        CreerUtilisateurRequest $request
    ): JsonResponse {
        $utilisateur = $this->gestionUtilisateurs->creer($request->validated());

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur créé.'])
            ->response()
            ->setStatusCode(201);
    }

    public function modifier(
        ModifierUtilisateurRequest $request,
        string $uuid
    ): UtilisateurResource {
        $utilisateur = $this->gestionUtilisateurs->trouverAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('modifier', $utilisateur);

        $utilisateur = $this->gestionUtilisateurs->modifier($utilisateur, $request->validated());

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur modifié.']);
    }

    public function activer(
        Request $request,
        string $uuid
    ): UtilisateurResource {
        $utilisateur = $this->gestionUtilisateurs->trouverAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('activer', $utilisateur);

        $utilisateur = $this->gestionUtilisateurs->activer($utilisateur);

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur activé.']);
    }

    public function desactiver(
        Request $request,
        string $uuid
    ): UtilisateurResource {
        $utilisateur = $this->gestionUtilisateurs->trouverAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('desactiver', $utilisateur);

        $utilisateur = $this->gestionUtilisateurs->desactiver($utilisateur);

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur désactivé.']);
    }

    public function permissionsUtilisateur(
        Request $request,
        string $uuid,
        CatalogueRolesPermissionsService $catalogue        
    ): JsonResponse {
        $utilisateur = $this->gestionUtilisateurs->trouverAccessibleParUuid(
            $request->user(),
            $uuid
        );
        $this->authorize('consulter', $utilisateur);

        return response()->json([
            'data' => [
                'utilisateur_uuid' => $utilisateur->uuid,
                'role' => $utilisateur->role->value,
                'permissions' => $catalogue->permissionsDeUtilisateur($utilisateur),
            ],
        ]);
    }
}
