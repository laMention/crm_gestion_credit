<?php

namespace Tests\Feature\Api\V1;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_sanctum_token_and_the_user_role(): void
    {
        $agence = Agence::query()->create([
            'code_agence' => 'AG-TEST-1',
            'nom_agence' => 'Agence de test',
            'adresse_agence' => 'Centre-ville',
            'ville_agence' => 'Abidjan',
            'telephone_agence' => '0700000000',
            'email_agence' => 'test-agence@example.test',
            'actif' => true,
        ]);
        $user = User::factory()->create([
            'agence_id' => $agence->id,
            'nom' => 'Kouassi',
            'prenom' => 'Aminata',
            'email' => 'aminata@example.test',
            'password' => 'correct-password',
            'role' => 'conseiller_client',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => '  AMINATA@example.test ',
            'password' => 'correct-password',
            'device_name' => 'test-phone',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Connexion réussie.')
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.uuid', $user->uuid)
            ->assertJsonPath('data.user.role', 'conseiller_client')
            ->assertJsonPath('data.user.agence.uuid', $agence->uuid)
            ->assertJsonMissingPath('data.user.password');

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'test-phone',
        ]);
        $this->assertNotNull($user->fresh()->derniere_connexion_le);
    }

    public function test_login_returns_401_for_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'user@example.test',
            'password' => 'correct-password',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'user@example.test',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Adresse e-mail ou mot de passe invalide.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_401_for_an_inactive_user(): void
    {
        User::factory()->create([
            'email' => 'inactive@example.test',
            'password' => 'correct-password',
            'actif' => false,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'inactive@example.test',
            'password' => 'correct-password',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_returns_429_after_five_failed_attempts(): void
    {
        User::factory()->create([
            'email' => 'locked@example.test',
            'password' => 'correct-password',
        ]);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'locked@example.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'locked@example.test',
            'password' => 'correct-password',
        ])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After');
    }

    public function test_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/profile')->assertUnauthorized();
    }

    public function test_profile_returns_the_authenticated_user_and_role(): void
    {
        $user = User::factory()->create([
            'nom' => 'Kouassi',
            'prenom' => 'Aminata',
            'role' => 'caissier',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/auth/profile')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.role', 'caissier')
            ->assertJsonPath('data.email', $user->email);
    }

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('current-device');
        $otherToken = $user->createToken('other-device');

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Déconnexion réussie.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $otherToken->accessToken->id,
        ]);

        Auth::forgetGuards();
        $this->withToken($token->plainTextToken)
            ->getJson('/api/v1/auth/profile')
            ->assertUnauthorized();
    }

    public function test_login_validates_required_credentials(): void
    {
        $this->postJson('/api/v1/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
