<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(
        LoginRequest $request,
        AuthService $authService
    ): JsonResponse {
        $credentials = $request->validated();

        return response()->json([
            'message' => 'Connexion réussie.',
            'data' => $authService->login(
                $credentials['email'],
                $credentials['password'],
                $request->ip() ?? 'unknown',
                $credentials['device_name'] ?? 'CRM API'
            ),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $accessToken = $request->user()->currentAccessToken();

        if ($accessToken instanceof PersonalAccessToken) {
            $accessToken->delete();
        }

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }

    public function profile(
        Request $request,
        AuthService $authService
    ): JsonResponse {        
        $user = $request->user();

        return response()->json([
            'data' => $authService->userProfile($user),
        ]);
    }
}
