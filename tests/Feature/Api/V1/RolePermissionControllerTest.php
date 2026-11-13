<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_and_permission_catalogues_require_authentication(): void
    {
        $this->getJson('/api/v1/roles')->assertUnauthorized();
        $this->getJson('/api/v1/permissions')->assertUnauthorized();
        $this->getJson('/api/v1/auth/permissions')->assertUnauthorized();
    }

    public function test_role_list_contains_the_five_fixed_roles_and_permissions(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);

        $this->actingAs($administrateur, 'sanctum')
            ->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.id', 'administrateur')
            ->assertJsonPath('data.0.libelle', 'Administrateur')
            ->assertJsonPath('data.0.permissions.0.module', 'agences')
            ->assertJsonMissingPath('data.5');
    }

    public function test_role_detail_returns_permissions_and_unknown_role_is_not_found(): void
    {
        $direction = User::factory()->create([
            'role' => UserRole::DIRECTION,
            'agence_id' => null,
        ]);

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/roles/conseiller')
            ->assertOk()
            ->assertJsonPath('data.id', 'conseiller')
            ->assertJsonPath('data.libelle', 'Conseiller')
            ->assertJsonPath('data.permissions.0.module', 'agences');

        $this->getJson('/api/v1/roles/invented')->assertNotFound();
    }

    public function test_role_catalogue_does_not_grant_direction_or_administrator_write_financial_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/roles/administrateur')
            ->assertOk()
            ->assertJsonMissing([
                'identifiant' => 'effectuerDepot',
            ])
            ->assertJsonMissing([
                'identifiant' => 'approuver',
            ]);

        $this->getJson('/api/v1/roles/direction')
            ->assertOk()
            ->assertJsonMissing([
                'identifiant' => 'creer',
            ])
            ->assertJsonFragment([
                'identifiant' => 'exporterTout',
            ]);
    }

    public function test_permission_catalogue_is_grouped_by_all_business_modules(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/permissions')
            ->assertOk()
            ->assertJsonCount(9, 'data')
            ->assertJsonPath('data.0.id', 'agences')
            ->assertJsonPath('data.1.id', 'utilisateurs')
            ->assertJsonPath('data.8.id', 'journaux_audit');
    }

    public function test_permission_detail_uses_the_backend_catalogue(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/permissions/credits.approuver')
            ->assertOk()
            ->assertJsonPath('data.id', 'credits.approuver')
            ->assertJsonPath('data.identifiant', 'approuver')
            ->assertJsonPath('data.module', 'credits');

        $this->getJson('/api/v1/permissions/credits.inventer')
            ->assertNotFound();
    }

    public function test_authenticated_user_receives_the_effective_permissions_for_their_role(): void
    {
        $agence = $this->creerAgence();
        $caissier = User::factory()->create([
            'role' => UserRole::CAISSIER,
            'agence_id' => $agence->id,
        ]);

        $this->actingAs($caissier, 'sanctum')
            ->getJson('/api/v1/auth/permissions')
            ->assertOk()
            ->assertJsonPath('data.utilisateur_uuid', $caissier->uuid)
            ->assertJsonPath('data.role', 'caissier')
            ->assertJsonFragment([
                'id' => 'comptes.effectuer_depot',
                'identifiant' => 'effectuerDepot',
            ])
            ->assertJsonMissing([
                'id' => 'credits.approuver',
                'identifiant' => 'approuver',
            ]);
    }

    public function test_branch_manager_can_consult_permissions_only_for_users_in_their_agency(): void
    {
        $agenceLocale = $this->creerAgence();
        $agenceAutre = $this->creerAgence('AG-ROLE-2');
        $chefAgence = User::factory()->create([
            'role' => UserRole::CHEF_AGENCE,
            'agence_id' => $agenceLocale->id,
        ]);
        $utilisateurAutreAgence = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agenceAutre->id,
        ]);

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/users/'.$utilisateurAutreAgence->uuid.'/permissions')
            ->assertNotFound();
    }

    private function creerAgence(string $code = 'AG-ROLE-1'): Agence
    {
        return Agence::query()->create([
            'code_agence' => $code,
            'nom_agence' => 'Agence '.$code,
            'adresse_agence' => 'Centre-ville',
            'ville_agence' => 'Abidjan',
            'telephone_agence' => '0700000000',
            'email_agence' => strtolower($code).'@example.test',
            'actif' => true,
        ]);
    }
}
