<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionAgencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_routes_require_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/agences')->assertUnauthorized();
        $this->postJson('/api/v1/agences')->assertUnauthorized();
    }

    public function test_administrator_creates_agency_with_safe_fields_and_audit_entry(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $donnees = $this->donneesAgence();
        $donnees['id'] = 999;
        $donnees['uuid'] = 'client-controlled-uuid';
        $donnees['actif'] = false;

        $reponse = $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/agences', $donnees);

        $reponse
            ->assertCreated()
            ->assertJsonPath('data.code_agence', 'AG-API-1')
            ->assertJsonPath('data.actif', true)
            ->assertJsonMissingPath('data.deleted_at')
            ->assertJsonStructure(['data' => ['id', 'uuid', 'code_agence', 'nom_agence']]);

        $agence = Agence::query()->where('code_agence', 'AG-API-1')->firstOrFail();
        $this->assertDatabaseHas('agences', [
            'id' => $agence->id,
            'email_agence' => $donnees['email_agence'],
            'actif' => true,
        ]);
        $this->assertNotSame(999, $agence->id);
        $this->assertNotSame('client-controlled-uuid', $agence->uuid);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'agence.cree',
            'module' => 'agences',
            'table_concernee' => 'agences',
            'enregistrement_id' => $agence->id,
            'agence_id' => $agence->id,
        ]);
    }

    public function test_agency_creation_rejects_duplicate_code_and_email_with_clear_errors(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $this->creerAgence('AG-API-2');

        $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/agences', $this->donneesAgence([
                'code_agence' => 'AG-API-2',
                'email_agence' => 'ag-api-2@example.test',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.code_agence.0', 'Ce code agence est déjà utilisé.')
            ->assertJsonPath('errors.email_agence.0', 'Cette adresse e-mail est déjà utilisée.');
    }

    public function test_agency_list_filters_and_paginates_network_results(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $cible = $this->creerAgence('AG-FILTRE-1', [
            'nom_agence' => 'Agence Centre',
            'actif' => true,
        ]);
        $this->creerAgence('AG-FILTRE-2', [
            'nom_agence' => 'Agence Centre Annexe',
            'actif' => true,
        ]);
        $this->creerAgence('AG-FILTRE-3', [
            'nom_agence' => 'Agence Centre Fermée',
            'actif' => false,
        ]);

        $this->actingAs($administrateur, 'sanctum')
            ->getJson('/api/v1/agences?recherche=Centre&actif=1&per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonFragment(['uuid' => $cible->uuid]);
    }

    public function test_branch_manager_sees_only_their_agency_and_foreign_agency_returns_404(): void
    {
        $agenceLocale = $this->creerAgence('AG-SCOPE-1');
        $agenceAutre = $this->creerAgence('AG-SCOPE-2');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/agences')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['uuid' => $agenceLocale->uuid])
            ->assertJsonMissing(['uuid' => $agenceAutre->uuid]);

        $this->getJson('/api/v1/agences/'.$agenceAutre->uuid)->assertNotFound();

        $this->patchJson('/api/v1/agences/'.$agenceAutre->uuid, [
            'nom_agence' => 'Modification interdite',
        ])->assertNotFound();
    }

    public function test_direction_can_consult_but_cannot_create_or_modify_agencies(): void
    {
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $agence = $this->creerAgence('AG-LECTURE-1');

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/agences')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $agence->uuid]);

        $this->postJson('/api/v1/agences', $this->donneesAgence())
            ->assertForbidden();
        $this->patchJson('/api/v1/agences/'.$agence->uuid, [
            'nom_agence' => 'Modification interdite',
        ])->assertForbidden();

        $this->assertDatabaseHas('agences', [
            'id' => $agence->id,
            'nom_agence' => $agence->nom_agence,
        ]);
    }

    public function test_administrator_can_modify_and_deactivate_an_agency_without_deleting_its_users(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $agence = $this->creerAgence('AG-UPDATE-1');
        $utilisateur = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);

        $this->actingAs($administrateur, 'sanctum')
            ->patchJson('/api/v1/agences/'.$agence->uuid, [
                'nom_agence' => 'Agence mise à jour',
                'actif' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.nom_agence', 'Agence mise à jour')
            ->assertJsonPath('data.actif', false);

        $this->assertDatabaseHas('agences', [
            'id' => $agence->id,
            'nom_agence' => 'Agence mise à jour',
            'actif' => false,
        ]);
        $this->assertDatabaseHas('users', [
            'id' => $utilisateur->id,
            'agence_id' => $agence->id,
            'actif' => true,
        ]);
        $this->assertTrue(
            AuditLog::query()
                ->where('action', 'agence.modifiee')
                ->where('enregistrement_id', $agence->id)
                ->exists()
        );
    }

    public function test_agency_creation_rejects_missing_and_invalid_fields(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);

        $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/agences', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code_agence',
                'nom_agence',
                'adresse_agence',
                'ville_agence',
                'telephone_agence',
                'email_agence',
            ]);

        $this->postJson('/api/v1/agences', $this->donneesAgence([
            'code_agence' => 'CODE-AGENCE-TROP-LONG',
            'email_agence' => 'adresse-invalide',
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code_agence', 'email_agence']);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerUtilisateur(
        UserRole $role,
        ?Agence $agence = null,
        array $remplacements = []
    ): User {
        return User::factory()->create(array_merge([
            'role' => $role,
            'agence_id' => $agence?->id,
        ], $remplacements));
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerAgence(string $code, array $remplacements = []): Agence
    {
        return Agence::query()->create(array_merge([
            'code_agence' => $code,
            'nom_agence' => 'Agence '.$code,
            'adresse_agence' => 'Centre-ville',
            'ville_agence' => 'Abidjan',
            'telephone_agence' => '0700000000',
            'email_agence' => strtolower($code).'@example.test',
            'actif' => true,
        ], $remplacements));
    }

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donneesAgence(array $remplacements = []): array
    {
        return array_merge([
            'code_agence' => 'AG-API-1',
            'nom_agence' => 'Agence API',
            'adresse_agence' => '12 rue du Plateau',
            'ville_agence' => 'Abidjan',
            'telephone_agence' => '0701234567',
            'email_agence' => 'ag-api-1@example.test',
        ], $remplacements);
    }
}
