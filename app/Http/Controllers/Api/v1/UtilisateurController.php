<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreerUtilisateurRequest;
use App\Http\Requests\Api\V1\ListerUtilisateursRequest;
use App\Http\Requests\Api\V1\ModifierUtilisateurRequest;
use App\Http\Resources\Api\v1\UtilisateurResource;
use App\Models\User;
use App\Services\Authorization\CatalogueRolesPermissionsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UtilisateurController extends Controller
{
    public function lister(
        ListerUtilisateursRequest $request
    ): AnonymousResourceCollection {
        $utilisateurs = User::query()
            ->accessiblePar($request->user())
            ->with('agence:id,uuid,code_agence,nom_agence,ville_agence')
            ->when(
                $request->filled('recherche'),
                function (Builder $requete, string $recherche): void {
                    $terme = '%'.$recherche.'%';
                    $requete->where(function (Builder $requeteRecherche) use ($terme): void {
                        $requeteRecherche
                            ->where('nom', 'like', $terme)
                            ->orWhere('prenom', 'like', $terme)
                            ->orWhere('email', 'like', $terme)
                            ->orWhere('telephone', 'like', $terme);
                    });
                }
            )
            ->when(
                $request->validated('role'),
                fn (Builder $requete, string $role): Builder => $requete->where('role', $role)
            )
            ->when(
                $request->validated('agence_id'),
                fn (Builder $requete, int $agenceId): Builder => $requete->where(
                    'agence_id',
                    $agenceId
                )
            )
            ->when(
                $request->exists('actif'),
                fn (Builder $requete): Builder => $requete->where(
                    'actif',
                    $request->validated('actif')
                )
            )
            ->orderBy('nom')
            ->orderBy('prenom')
            ->orderBy('id')
            ->paginate($request->validated('per_page', 15));

        return UtilisateurResource::collection($utilisateurs)
            ->additional(['message' => 'Liste des utilisateurs.']);
    }

    public function consulter(Request $request, string $uuid): UtilisateurResource
    {
        $utilisateur = $this->trouverUtilisateurAccessible($request, $uuid);
        $this->authorize('consulter', $utilisateur);

        return new UtilisateurResource($utilisateur);
    }

    public function creer(
        CreerUtilisateurRequest $request
    ): JsonResponse {
        $utilisateur = User::query()->create($request->validated());
        $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur créé.'])
            ->response()
            ->setStatusCode(201);
    }

    public function modifier(
        ModifierUtilisateurRequest $request,
        string $uuid
    ): UtilisateurResource {
        $utilisateur = $this->trouverUtilisateurAccessible($request, $uuid);
        $this->authorize('modifier', $utilisateur);

        $utilisateur->fill($request->validated())->save();
        $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur modifié.']);
    }

    public function activer(Request $request, string $uuid): UtilisateurResource
    {
        $utilisateur = $this->trouverUtilisateurAccessible($request, $uuid);
        $this->authorize('activer', $utilisateur);

        $utilisateur->forceFill(['actif' => true])->save();
        $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur activé.']);
    }

    public function desactiver(Request $request, string $uuid): UtilisateurResource
    {
        $utilisateur = $this->trouverUtilisateurAccessible($request, $uuid);
        $this->authorize('desactiver', $utilisateur);

        $utilisateur->forceFill(['actif' => false])->save();
        $utilisateur->tokens()->delete();
        $utilisateur->load('agence:id,uuid,code_agence,nom_agence,ville_agence');

        return (new UtilisateurResource($utilisateur))
            ->additional(['message' => 'Utilisateur désactivé.']);
    }

    public function permissionsUtilisateur(
        Request $request,
        string $uuid,
        CatalogueRolesPermissionsService $catalogue
    ): JsonResponse {
        $utilisateur = $this->trouverUtilisateurAccessible($request, $uuid);
        $this->authorize('consulter', $utilisateur);

        return response()->json([
            'data' => [
                'utilisateur_uuid' => $utilisateur->uuid,
                'role' => $utilisateur->role->value,
                'permissions' => $catalogue->permissionsDeUtilisateur($utilisateur),
            ],
        ]);
    }

    private function trouverUtilisateurAccessible(
        Request $request,
        string $uuid
    ): User {
        return User::query()
            ->accessiblePar($request->user())
            ->with('agence:id,uuid,code_agence,nom_agence,ville_agence')
            ->where('uuid', $uuid)
            ->firstOrFail();
    }
}
