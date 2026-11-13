use App\Models\User;
<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ClientController;
use App\Http\Controllers\Api\v1\CompteController;
use App\Http\Controllers\Api\v1\RolePermissionController;
use App\Http\Controllers\Api\v1\UtilisateurController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:10,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('profile', [AuthController::class, 'profile']);
            Route::get('permissions', [RolePermissionController::class, 'permissionsConnecte']);
        });
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('clients', [ClientController::class, 'lister']);
        Route::post('clients', [ClientController::class, 'creer']);
        Route::get('clients/{uuid}', [ClientController::class, 'consulter']);
        Route::put('clients/{uuid}', [ClientController::class, 'modifier']);
        Route::patch('clients/{uuid}', [ClientController::class, 'modifier']);

        Route::get('comptes', [CompteController::class, 'lister']);
        Route::post('clients/{uuid}/comptes', [CompteController::class, 'ouvrir']);
        Route::get('comptes/{uuid}/releve', [CompteController::class, 'releve']);
        Route::get('comptes/{uuid}', [CompteController::class, 'consulter']);

        Route::get('users', [UtilisateurController::class, 'lister']);
        Route::post('users', [UtilisateurController::class, 'creer']);
        Route::get('users/{uuid}/permissions', [UtilisateurController::class, 'permissionsUtilisateur']);
        Route::get('users/{uuid}', [UtilisateurController::class, 'consulter']);
        Route::put('users/{uuid}', [UtilisateurController::class, 'modifier']);
        Route::patch('users/{uuid}', [UtilisateurController::class, 'modifier']);
        Route::patch('users/{uuid}/activer', [UtilisateurController::class, 'activer']);
        Route::patch('users/{uuid}/desactiver', [UtilisateurController::class, 'desactiver']);

        Route::get('roles', [RolePermissionController::class, 'listerRoles']);
        Route::get('roles/{role}', [RolePermissionController::class, 'consulterRole']);
        Route::get('permissions', [RolePermissionController::class, 'listerPermissions']);
        Route::get('permissions/{permission}', [
            RolePermissionController::class,
            'consulterPermission',
        ]);
    });
});
