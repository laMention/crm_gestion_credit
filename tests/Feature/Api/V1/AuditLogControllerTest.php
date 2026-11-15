<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_manager_can_only_consult_audit_logs_from_their_agency(): void
    {
        $agenceLocale = $this->creerAgence('AG-AUD-1');
        $agenceAutre = $this->creerAgence('AG-AUD-2');
        $chef = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $utilisateurLocal = $this->creerUtilisateur(UserRole::CAISSIER, $agenceLocale);
        $utilisateurAutre = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceAutre);
        $journalLocal = app(AuditLogService::class)->enregistrer(
            $utilisateurLocal,
            'transaction.depot',
            'transactions',
            'transaction',
            1,
            [],
            ['reference_transaction' => 'TRX-LOCAL'],
            agenceId: $agenceLocale->id
        );
        $journalAutre = app(AuditLogService::class)->enregistrer(
            $utilisateurAutre,
            'client.cree',
            'clients',
            'client',
            2,
            agenceId: $agenceAutre->id
        );

        $this->actingAs($chef, 'sanctum')
            ->getJson('/api/v1/journaux-audit')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.agence_id', $agenceLocale->id);

        $this->getJson('/api/v1/journaux-audit/'.$journalAutre->uuid)
            ->assertNotFound();
        $this->getJson('/api/v1/journaux-audit/'.$journalLocal->uuid)
            ->assertOk()
            ->assertJsonPath('data.role_utilisateur', 'caissier')
            ->assertJsonPath('data.action', 'transaction.depot');
    }

    public function test_adviser_and_cashier_are_forbidden_from_audit_logs(): void
    {
        $agence = $this->creerAgence('AG-AUD-3');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/journaux-audit')
            ->assertForbidden();
        $this->actingAs($caissier, 'sanctum')
            ->getJson('/api/v1/journaux-audit')
            ->assertForbidden();
    }

    public function test_sensitive_login_data_is_not_written_to_audit_values(): void
    {
        $agence = $this->creerAgence('AG-AUD-4');
        $utilisateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR, $agence);

        $journal = app(AuditLogService::class)->enregistrer(
            $utilisateur,
            'utilisateur.modifie',
            'utilisateurs',
            'utilisateur',
            $utilisateur->id,
            ['password' => 'hashed-password', 'role' => 'conseiller'],
            [
                'token' => 'secret-token',
                'access_token' => 'secret-access',
                'api_key' => 'secret-key',
                'role' => 'administrateur',
            ]
        );

        $this->assertSame(['role' => 'conseiller'], $journal->anciennes_valeurs);
        $this->assertSame(['role' => 'administrateur'], $journal->nouvelles_valeurs);

        $this->actingAs($utilisateur, 'sanctum')
            ->getJson('/api/v1/journaux-audit/'.$journal->uuid)
            ->assertOk()
            ->assertJsonMissingPath('data.nouvelles_valeurs.token')
            ->assertJsonMissingPath('data.anciennes_valeurs.password');
    }

    public function test_audit_entries_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $utilisateur = $this->creerUtilisateur(
            UserRole::ADMINISTRATEUR,
            $this->creerAgence('AG-AUD-5')
        );
        $journal = app(AuditLogService::class)->enregistrer(
            $utilisateur,
            'test.evenement',
            'utilisateurs',
            'utilisateur',
            $utilisateur->id
        );

        try {
            $journal->forceFill(['description' => 'modifié'])->save();
            $this->fail('Une entrée d’audit ne doit pas être modifiable.');
        } catch (LogicException $exception) {
            $this->assertSame('Les journaux d’audit sont immuables.', $exception->getMessage());
        }

        $journal = AuditLog::query()->findOrFail($journal->id);
        try {
            $journal->delete();
            $this->fail('Une entrée d’audit ne doit pas être supprimable.');
        } catch (LogicException $exception) {
            $this->assertSame('Les journaux d’audit sont immuables.', $exception->getMessage());
        }
    }

    public function test_audit_api_has_no_mutation_routes(): void
    {
        $utilisateur = $this->creerUtilisateur(
            UserRole::ADMINISTRATEUR,
            $this->creerAgence('AG-AUD-6')
        );

        $this->actingAs($utilisateur, 'sanctum')
            ->deleteJson('/api/v1/journaux-audit/not-a-real-record')
            ->assertMethodNotAllowed();
    }

    private function creerUtilisateur(UserRole $role, Agence $agence): User
    {
        return User::factory()->create([
            'role' => $role,
            'agence_id' => $agence->id,
        ]);
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
}
