<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Http\Requests\Api\V1\CreerUtilisateurRequest;
use App\Http\Requests\Api\V1\ModifierUtilisateurRequest;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class CreerUtilisateurRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_creation_requires_an_agency(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($administrateur)
            ->postJson('/_tests/utilisateurs', $this->donneesUtilisateur([
                'role' => UserRole::CONSEILLER->value,
            ]))
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.agence_id.0',
                'Une agence est obligatoire pour ce rôle.'
            );
    }

    public function test_network_user_can_be_created_without_an_agency(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($administrateur)
            ->postJson('/_tests/utilisateurs', $this->donneesUtilisateur([
                'role' => UserRole::DIRECTION->value,
            ]))
            ->assertOk()
            ->assertJsonPath('data.role', UserRole::DIRECTION->value);
    }

    public function test_legacy_role_value_is_rejected(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($administrateur)
            ->postJson('/_tests/utilisateurs', $this->donneesUtilisateur([
                'role' => 'conseiller_client',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    }

    public function test_only_administrators_can_create_users(): void
    {
        $agence = $this->creerAgence();
        $conseiller = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agence->id,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($conseiller)
            ->postJson('/_tests/utilisateurs', $this->donneesUtilisateur([
                'role' => UserRole::DIRECTION->value,
            ]))
            ->assertForbidden();
    }

    public function test_staff_user_cannot_be_updated_without_an_agency(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);
        $agence = $this->creerAgence();
        $utilisateur = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agence->id,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($administrateur)
            ->putJson('/_tests/utilisateurs/'.$utilisateur->uuid, [
                'agence_id' => null,
            ])
            ->assertUnprocessable()
            ->assertJsonPath(
                'errors.agence_id.0',
                'Une agence est obligatoire pour ce rôle.'
            );
    }

    public function test_direction_cannot_modify_a_user(): void
    {
        $agence = $this->creerAgence();
        $direction = User::factory()->create([
            'role' => UserRole::DIRECTION,
            'agence_id' => null,
        ]);
        $utilisateur = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agence->id,
        ]);
        $this->enregistrerRouteDeTest();

        $this->actingAs($direction)
            ->putJson('/_tests/utilisateurs/'.$utilisateur->uuid, [
                'nom' => 'Nouveau nom',
            ])
            ->assertForbidden();
    }

    private function enregistrerRouteDeTest(): void
    {
        Route::post('/_tests/utilisateurs', function (CreerUtilisateurRequest $request) {
            return response()->json([
                'data' => $request->validated(),
            ]);
        });

        Route::put('/_tests/utilisateurs/{utilisateur}', function (
            User $utilisateur,
            ModifierUtilisateurRequest $request
        ) {
            return response()->json([
                'data' => $request->validated(),
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donneesUtilisateur(array $remplacements = []): array
    {
        return array_merge([
            'nom' => 'Kouassi',
            'prenom' => 'Aminata',
            'adresse' => 'Centre-ville',
            'telephone' => '0700000000',
            'ville' => 'Abidjan',
            'email' => 'nouveau.utilisateur@example.test',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
            'role' => UserRole::ADMINISTRATEUR->value,
        ], $remplacements);
    }

    private function creerAgence(): Agence
    {
        return Agence::query()->create([
            'code_agence' => 'AG-REQ-1',
            'nom_agence' => 'Agence de requête',
            'adresse_agence' => 'Centre-ville',
            'ville_agence' => 'Abidjan',
            'telephone_agence' => '0700000000',
            'email_agence' => 'agence-requete@example.test',
            'actif' => true,
        ]);
    }
}
