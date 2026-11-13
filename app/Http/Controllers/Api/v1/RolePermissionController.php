<?php

namespace App\Http\Controllers\Api\v1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Authorization\CatalogueRolesPermissionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    protected CatalogueRolesPermissionsService $catalogue;

    public function __construct(CatalogueRolesPermissionsService $catalogue)
    {
        $this->catalogue = $catalogue;
    }

    public function listerRoles(): JsonResponse {
        $roles = array_map(
            fn (UserRole $role): array => $this->catalogue->roleDefini($role),
            UserRole::cases()
        );

        return response()->json(['data' => $roles]);
    }

    public function consulterRole(string $role): JsonResponse {
        $roleDefini = UserRole::tryFrom($role);

        abort_if($roleDefini === null, 404);

        return response()->json([
            'data' => $this->catalogue->roleDefini($roleDefini),
        ]);
    }

    public function listerPermissions(): JsonResponse {
        $modules = [];

        foreach ($this->catalogue->permissionsDisponibles() as $permission) {
            $modules[$permission['module']] ??= [
                'id' => $permission['module'],
                'libelle' => $permission['module_libelle'],
                'permissions' => [],
            ];
            $modules[$permission['module']]['permissions'][] = $permission;
        }

        return response()->json([
            'data' => array_values($modules),
        ]);
    }

    public function consulterPermission(string $permission): JsonResponse {
        $definition = $this->catalogue->permissionDefinie($permission);

        abort_if($definition === null, 404);

        return response()->json(['data' => $definition]);
    }

    public function permissionsConnecte(Request $request): JsonResponse {
        $user = $request->user();

        return response()->json([
            'data' => [
                'utilisateur_uuid' => $user->uuid,
                'role' => $user->role->value,
                'permissions' => $this->catalogue->permissionsDeUtilisateur($user),
            ],
        ]);
    }
}
