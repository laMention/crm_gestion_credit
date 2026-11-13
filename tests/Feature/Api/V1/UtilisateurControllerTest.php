<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UtilisateurControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_management_requires_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/users')->assertUnauthorized();
        $this->postJson('/api/v1/users')->assertUnauthorized();
    }

    public function test_administrator_creates_user_and_returns_only_safe_fields(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $agence = $this->creerAgence('AG-API-1');
        $donnees = $this->donneesUtilisateur([
            'role' => UserRole::CONSEILLER->value,
            'agence_id' => $agence->id,
        ]);

        $reponse = $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/users', $donnees);

        $reponse
            ->assertCreated()
            ->assertJsonPath('data.role', 'conseiller')
            ->assertJsonPath('data.agence.uuid', $agence->uuid)
            ->assertJsonMissingPath('data.password');

        $utilisateur = User::query()->where('email', $donnees['email'])->firstOrFail();
        $this->assertTrue(Hash::check($donnees['password'], $utilisateur->password));
        $this->assertDatabaseHas('users', [
            'id' => $utilisateur->id,
            'role' => 'conseiller',
            'agence_id' => $agence->id,
        ]);
    }

    public function test_user_creation_rejects_an_existing_email(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $this->creerUtilisateur(UserRole::DIRECTION, null, [
            'email' => 'deja.utilise@example.test',
        ]);

        $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/users', $this->donneesUtilisateur([
                'email' => 'deja.utilise@example.test',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_direction_can_list_network_users_but_cannot_change_them(): void
    {
        $agence = $this->creerAgence('AG-API-2');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $utilisateur = $this->creerUtilisateur(UserRole::CONSEILLER, $agence, [
            'email' => 'conseiller-api@example.test',
        ]);

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $utilisateur->uuid]);

        $this->actingAs($direction, 'sanctum')
            ->patchJson('/api/v1/users/'.$utilisateur->uuid, ['nom' => 'Modifié'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $utilisateur->id,
            'nom' => $utilisateur->nom,
        ]);
    }

    public function test_branch_manager_is_limited_to_their_agency_and_foreign_uuid_returns_404(): void
    {
        $agenceLocale = $this->creerAgence('AG-API-3');
        $agenceAutre = $this->creerAgence('AG-API-4');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $utilisateurLocal = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceLocale, [
            'email' => 'local-api@example.test',
        ]);
        $utilisateurAutreAgence = $this->creerUtilisateur(
            UserRole::CAISSIER,
            $agenceAutre,
            ['email' => 'autre-api@example.test']
        );

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $utilisateurLocal->uuid])
            ->assertJsonMissing(['uuid' => $utilisateurAutreAgence->uuid]);

        $this->getJson('/api/v1/users/'.$utilisateurAutreAgence->uuid)
            ->assertNotFound();

        $this->patchJson('/api/v1/users/'.$utilisateurAutreAgence->uuid, [
            'nom' => 'Tentative hors agence',
        ])->assertNotFound();
    }

    public function test_administrator_filters_users_and_paginates_results(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $agence = $this->creerAgence('AG-API-5');
        $cible = $this->creerUtilisateur(UserRole::CONSEILLER, $agence, [
            'nom' => 'Traore',
            'prenom' => 'Awa',
            'email' => 'awa.traore@example.test',
            'telephone' => '0701111111',
            'actif' => true,
        ]);
        $this->creerUtilisateur(UserRole::CONSEILLER, $agence, [
            'nom' => 'Traore',
            'prenom' => 'Aïcha',
            'email' => 'aicha.traore@example.test',
            'telephone' => '0701111112',
            'actif' => true,
        ]);
        $this->creerUtilisateur(UserRole::CONSEILLER, $agence, [
            'nom' => 'Traore',
            'prenom' => 'Adama',
            'email' => 'adama.traore@example.test',
            'telephone' => '0702222222',
            'actif' => false,
        ]);
        $autreAgence = $this->creerAgence('AG-API-6');
        $this->creerUtilisateur(UserRole::CONSEILLER, $autreAgence, [
            'nom' => 'Traore',
            'email' => 'autre.traore@example.test',
            'telephone' => '0703333333',
        ]);

        $this->actingAs($administrateur, 'sanctum')
            ->getJson('/api/v1/users?recherche=Traore&role=conseiller&agence_id='.$agence->id.'&actif=1&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonFragment(['uuid' => $cible->uuid]);
    }

    public function test_branch_manager_cannot_escape_agency_scope_with_list_filter(): void
    {
        $agenceLocale = $this->creerAgence('AG-API-7');
        $agenceAutre = $this->creerAgence('AG-API-8');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $utilisateurAutreAgence = $this->creerUtilisateur(
            UserRole::CONSEILLER,
            $agenceAutre,
            ['email' => 'filtre-autre@example.test']
        );

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/users?agence_id='.$agenceAutre->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonMissing(['uuid' => $utilisateurAutreAgence->uuid]);
    }

    public function test_user_list_rejects_invalid_role_and_page_size_filters(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);

        $this->actingAs($administrateur, 'sanctum')
            ->getJson('/api/v1/users?role=conseiller_client')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);

        $this->getJson('/api/v1/users?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_administrator_can_update_user_but_cannot_change_own_role_or_agency(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $agence = $this->creerAgence('AG-API-9');
        $utilisateur = $this->creerUtilisateur(UserRole::CONSEILLER, $agence, [
            'email' => 'avant.modification@example.test',
        ]);

        $this->actingAs($administrateur, 'sanctum')
            ->patchJson('/api/v1/users/'.$utilisateur->uuid, [
                'email' => 'apres.modification@example.test',
                'password' => 'nouveau-mot-de-passe',
                'password_confirmation' => 'nouveau-mot-de-passe',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'apres.modification@example.test')
            ->assertJsonMissingPath('data.password');

        $this->assertTrue(
            Hash::check('nouveau-mot-de-passe', $utilisateur->fresh()->password)
        );

        $this->patchJson('/api/v1/users/'.$administrateur->uuid, [
            'role' => UserRole::DIRECTION->value,
        ])->assertForbidden();

        $this->patchJson('/api/v1/users/'.$administrateur->uuid, [
            'agence_id' => $agence->id,
        ])->assertForbidden();
    }

    public function test_administrator_can_create_a_network_user_without_an_agency(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);

        $this->actingAs($administrateur, 'sanctum')
            ->postJson('/api/v1/users', $this->donneesUtilisateur([
                'role' => UserRole::DIRECTION->value,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.role', 'direction')
            ->assertJsonPath('data.agence', null);

        $this->assertDatabaseHas('users', [
            'email' => 'nouveau.user.api@example.test',
            'role' => 'direction',
            'agence_id' => null,
        ]);
    }

    public function test_administrator_can_activate_and_deactivate_a_user(): void
    {
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $agence = $this->creerAgence('AG-API-10');
        $utilisateur = $this->creerUtilisateur(UserRole::CAISSIER, $agence, [
            'email' => 'caissier-api@example.test',
            'actif' => true,
        ]);
        $jeton = $utilisateur->createToken('session-a-revoquer');

        $this->actingAs($administrateur, 'sanctum')
            ->patchJson('/api/v1/users/'.$utilisateur->uuid.'/desactiver')
            ->assertOk()
            ->assertJsonPath('data.actif', false);

        $this->assertDatabaseHas('users', [
            'id' => $utilisateur->id,
            'actif' => false,
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $jeton->accessToken->id,
        ]);

        $this->patchJson('/api/v1/users/'.$utilisateur->uuid.'/activer')
            ->assertOk()
            ->assertJsonPath('data.actif', true);

        $this->assertDatabaseHas('users', [
            'id' => $utilisateur->id,
            'actif' => true,
        ]);
    }

    public function test_unprivileged_roles_cannot_list_or_create_users(): void
    {
        $agence = $this->creerAgence('AG-API-11');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence, [
            'email' => 'caissier-limite@example.test',
        ]);

        foreach ([$conseiller, $caissier] as $utilisateur) {
            $this->actingAs($utilisateur, 'sanctum')
                ->getJson('/api/v1/users')
                ->assertForbidden();
            $this->postJson('/api/v1/users', $this->donneesUtilisateur())
                ->assertForbidden();
        }
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

    private function creerAgence(string $code): Agence
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
            'telephone' => '0704444444',
            'ville' => 'Abidjan',
            'email' => 'nouveau.user.api@example.test',
            'password' => 'correct-password',
            'password_confirmation' => 'correct-password',
            'role' => UserRole::ADMINISTRATEUR->value,
        ], $remplacements);
    }
}
