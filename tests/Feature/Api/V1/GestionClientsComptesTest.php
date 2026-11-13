<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionClientsComptesTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_and_account_endpoints_require_sanctum_authentication(): void
    {
        $this->getJson('/api/v1/clients')->assertUnauthorized();
        $this->getJson('/api/v1/comptes')->assertUnauthorized();
    }

    public function test_conseiller_creates_client_in_own_agency_and_safe_fields_are_returned(): void
    {
        $agence = $this->creerAgence('AG-CLI-1');
        $autreAgence = $this->creerAgence('AG-CLI-2');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/clients', $this->donneesClient([
                'agence_id' => $autreAgence->id,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agence_id']);

        $this->postJson('/api/v1/clients', $this->donneesClient())
            ->assertCreated()
            ->assertJsonPath('data.agence.uuid', $agence->uuid)
            ->assertJsonMissingPath('data.numero_piece_identite')
            ->assertJsonMissingPath('data.revenu_mensuel');

        $this->assertDatabaseHas('clients', [
            'email_client' => 'aminata.client@example.test',
            'agence_id' => $agence->id,
            'cree_par_user_id' => $conseiller->id,
            'statut' => 'actif',
        ]);
    }

    public function test_client_creation_rejects_an_existing_email(): void
    {
        $agence = $this->creerAgence('AG-CLI-7');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $this->creerClient($agence, [
            'email_client' => 'aminata.client@example.test',
        ]);

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/clients', $this->donneesClient())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email_client']);
    }

    public function test_caissier_cannot_create_or_modify_clients(): void
    {
        $agence = $this->creerAgence('AG-CLI-3');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $client = $this->creerClient($agence);

        $this->actingAs($caissier, 'sanctum')
            ->postJson('/api/v1/clients', $this->donneesClient())
            ->assertForbidden();

        $this->patchJson('/api/v1/clients/'.$client->uuid, [
            'nom_client' => 'Modification interdite',
        ])->assertForbidden();
    }

    public function test_conseiller_updates_client_information_without_changing_agency_or_status(): void
    {
        $agence = $this->creerAgence('AG-CLI-8');
        $autreAgence = $this->creerAgence('AG-CLI-9');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $client = $this->creerClient($agence);

        $this->actingAs($conseiller, 'sanctum')
            ->patchJson('/api/v1/clients/'.$client->uuid, [
                'nom_client' => 'Nom mis à jour',
                'agence_id' => $autreAgence->id,
                'statut' => 'bloque',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agence_id', 'statut']);

        $this->patchJson('/api/v1/clients/'.$client->uuid, [
            'nom_client' => 'Nom mis à jour',
        ])
            ->assertOk()
            ->assertJsonPath('data.nom_client', 'Nom mis à jour');

        $this->assertDatabaseHas('clients', [
            'id' => $client->id,
            'nom_client' => 'Nom mis à jour',
            'agence_id' => $agence->id,
            'statut' => 'actif',
        ]);
    }

    public function test_branch_manager_sees_only_agency_clients_and_foreign_uuid_is_not_found(): void
    {
        $agenceLocale = $this->creerAgence('AG-CLI-4');
        $agenceAutre = $this->creerAgence('AG-CLI-5');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $clientLocal = $this->creerClient($agenceLocale);
        $clientAutre = $this->creerClient($agenceAutre, [
            'email_client' => 'client.autre@example.test',
        ]);

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/clients')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $clientLocal->uuid])
            ->assertJsonMissing(['uuid' => $clientAutre->uuid]);

        $this->getJson('/api/v1/clients/'.$clientAutre->uuid)->assertNotFound();
        $this->patchJson('/api/v1/clients/'.$clientAutre->uuid, [
            'nom_client' => 'Modification inter-agence',
        ])->assertNotFound();
    }

    public function test_network_roles_can_consult_clients_from_all_agencies(): void
    {
        $agence = $this->creerAgence('AG-CLI-6');
        $client = $this->creerClient($agence);

        foreach ([UserRole::DIRECTION, UserRole::ADMINISTRATEUR] as $role) {
            $utilisateur = $this->creerUtilisateur($role);

            $this->actingAs($utilisateur, 'sanctum')
                ->getJson('/api/v1/clients/'.$client->uuid)
                ->assertOk()
                ->assertJsonPath('data.uuid', $client->uuid);
        }
    }

    public function test_conseiller_opens_unique_account_for_local_client(): void
    {
        $agence = $this->creerAgence('AG-CPT-1');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $client = $this->creerClient($agence);

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/clients/'.$client->uuid.'/comptes', [
                'type_compte' => 'courant',
            ])
            ->assertCreated()
            ->assertJsonPath('data.type_compte', 'courant')
            ->assertJsonPath('data.solde', '0.00')
            ->assertJsonPath('data.solde_initial', '0.00');

        $this->postJson('/api/v1/clients/'.$client->uuid.'/comptes', [
            'type_compte' => 'epargne',
        ])->assertCreated();

        $comptes = Compte::query()->where('client_id', $client->id)->get();
        $this->assertCount(2, $comptes);
        $this->assertSame(
            2,
            $comptes->pluck('numero_compte')->unique()->count()
        );
        $this->assertDatabaseHas('comptes', [
            'client_id' => $client->id,
            'agence_id' => $agence->id,
            'cree_par_user_id' => $conseiller->id,
        ]);
    }

    public function test_account_opening_rejects_credit_type_and_foreign_client(): void
    {
        $agence = $this->creerAgence('AG-CPT-2');
        $autreAgence = $this->creerAgence('AG-CPT-3');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $clientLocal = $this->creerClient($agence);
        $clientAutre = $this->creerClient($autreAgence, [
            'email_client' => 'client.autre.compte@example.test',
        ]);

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/clients/'.$clientLocal->uuid.'/comptes', [
                'type_compte' => 'credit',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['type_compte']);

        $this->postJson('/api/v1/clients/'.$clientAutre->uuid.'/comptes', [
            'type_compte' => 'courant',
        ])->assertNotFound();
    }

    public function test_cashier_cannot_open_an_account(): void
    {
        $agence = $this->creerAgence('AG-CPT-4');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $client = $this->creerClient($agence);

        $this->actingAs($caissier, 'sanctum')
            ->postJson('/api/v1/clients/'.$client->uuid.'/comptes', [
                'type_compte' => 'courant',
            ])
            ->assertForbidden();
    }

    public function test_account_list_and_detail_are_scoped_to_the_users_agency(): void
    {
        $agenceLocale = $this->creerAgence('AG-CPT-5');
        $agenceAutre = $this->creerAgence('AG-CPT-6');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceLocale);
        $compteLocal = $this->creerCompte($this->creerClient($agenceLocale));
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.autre.detail@example.test',
            ]),
            ['numero_compte' => 'C-FOREIGN-001']
        );

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/comptes')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $compteLocal->uuid])
            ->assertJsonMissing(['uuid' => $compteAutre->uuid]);

        $this->getJson('/api/v1/comptes/'.$compteAutre->uuid)->assertNotFound();
    }

    public function test_client_and_account_filters_return_only_matching_accessible_records(): void
    {
        $agenceLocale = $this->creerAgence('AG-FIL-1');
        $agenceAutre = $this->creerAgence('AG-FIL-2');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $clientLocal = $this->creerClient($agenceLocale, [
            'nom_client' => 'Recherche Cible',
            'statut' => 'inactif',
        ]);
        $clientAutre = $this->creerClient($agenceAutre, [
            'email_client' => 'client.filtre.autre@example.test',
            'nom_client' => 'Recherche Cible',
        ]);
        $compteLocal = $this->creerCompte($clientLocal, [
            'numero_compte' => 'C-FILTER-LOCAL',
            'statut_compte' => 'bloque',
        ]);
        $compteAutre = $this->creerCompte($clientAutre, [
            'numero_compte' => 'C-FILTER-OTHER',
        ]);

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/clients?recherche=Recherche%20Cible&statut=inactif')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['uuid' => $clientLocal->uuid])
            ->assertJsonMissing(['uuid' => $clientAutre->uuid]);

        $this->getJson('/api/v1/comptes?numero_compte=FILTER-LOCAL&client_uuid='.$clientLocal->uuid.'&statut_compte=bloque')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonFragment(['uuid' => $compteLocal->uuid])
            ->assertJsonMissing(['uuid' => $compteAutre->uuid]);

        $this->getJson('/api/v1/comptes?client_uuid='.$clientAutre->uuid)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->getJson('/api/v1/comptes?agence_id='.$agenceAutre->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agence_id']);

        $directeur = $this->creerUtilisateur(UserRole::DIRECTION);
        $this->actingAs($directeur, 'sanctum')
            ->getJson('/api/v1/comptes?agence_id='.$agenceLocale->id)
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_statement_reconciles_opening_balance_posted_movements_and_pagination(): void
    {
        $this->travelTo('2026-10-15 12:00:00');
        $agence = $this->creerAgence('AG-RLV-1');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $client = $this->creerClient($agence);
        $compte = $this->creerCompte($client, [
            'numero_compte' => 'C-STATEMENT-001',
            'solde' => '9999.99',
            'solde_initial' => '1000.00',
        ]);

        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PRE-CREDIT',
            'montant' => '200.00',
            'date_transaction' => '2026-10-01 09:00:00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PRE-DEBIT',
            'montant' => '50.00',
            'date_transaction' => '2026-10-02 09:00:00',
            'compte_source_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PRE-CENTIMES-1',
            'montant' => '0.10',
            'date_transaction' => '2026-10-03 09:00:00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PRE-CENTIMES-2',
            'montant' => '0.20',
            'date_transaction' => '2026-10-04 09:00:00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PER-CREDIT',
            'montant' => '125.25',
            'date_transaction' => '2026-10-10 09:00:00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PER-DEBIT',
            'montant' => '20.10',
            'date_transaction' => '2026-10-11 09:00:00',
            'compte_source_id' => $compte->id,
        ]);
        $compteAutre = $this->creerCompte($client, [
            'numero_compte' => 'C-STATEMENT-002',
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PER-VIR-SORTANT',
            'montant' => '30.05',
            'date_transaction' => '2026-10-12 09:00:00',
            'compte_source_id' => $compte->id,
            'compte_destinataire_id' => $compteAutre->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-PER-VIR-ENTRANT',
            'montant' => '40.00',
            'date_transaction' => '2026-10-13 09:00:00',
            'compte_source_id' => $compteAutre->id,
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-EN-ATTENTE',
            'montant' => '500.00',
            'date_transaction' => '2026-10-14 09:00:00',
            'compte_destinataire_id' => $compte->id,
            'statut_transaction' => 'en_attente',
            'date_validation' => null,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-VALIDATION-MANQUANTE',
            'montant' => '300.00',
            'date_transaction' => '2026-10-14 10:00:00',
            'compte_destinataire_id' => $compte->id,
            'date_validation' => null,
        ]);
        $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-ANNULEE',
            'montant' => '700.00',
            'date_transaction' => '2026-10-14 11:00:00',
            'compte_destinataire_id' => $compte->id,
            'statut_transaction' => 'annulee',
        ]);

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/comptes/'.$compte->uuid.'/releve?date_debut=2026-10-10&date_fin=2026-10-14&operations_par_page=2')
            ->assertOk()
            ->assertJsonPath('data.periode.solde_initial', '1150.30')
            ->assertJsonPath('data.totaux.total_credits', '165.25')
            ->assertJsonPath('data.totaux.total_debits', '50.15')
            ->assertJsonPath('data.totaux.solde_final', '1265.40')
            ->assertJsonPath('data.operations.total', 4)
            ->assertJsonPath('data.operations.data.0.sens', 'credit')
            ->assertJsonPath('data.operations.data.0.solde_apres_operation', '1275.55')
            ->assertJsonPath('data.operations.data.1.solde_apres_operation', '1255.45')
            ->assertJsonMissing(['reference_transaction' => 'TRX-EN-ATTENTE'])
            ->assertJsonMissing(['reference_transaction' => 'TRX-VALIDATION-MANQUANTE'])
            ->assertJsonMissing(['reference_transaction' => 'TRX-ANNULEE']);

        $this->getJson('/api/v1/comptes/'.$compte->uuid.'/releve?date_debut=2026-10-10&date_fin=2026-10-14&operations_par_page=2&page=2')
            ->assertOk()
            ->assertJsonPath('data.operations.data.0.solde_apres_operation', '1225.40')
            ->assertJsonPath('data.operations.data.1.solde_apres_operation', '1265.40');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '9999.99',
            'solde_initial' => '1000.00',
        ]);
    }

    public function test_statement_rejects_invalid_date_range_with_422(): void
    {
        $agence = $this->creerAgence('AG-RLV-2');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/comptes/'.$compte->uuid.'/releve?date_debut=2026-10-15&date_fin=2026-10-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut', 'date_fin']);

        $this->getJson('/api/v1/comptes/'.$compte->uuid.'/releve?date_debut=10-15-2026&date_fin=2026-10-31')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_debut']);
    }

    public function test_user_cannot_consult_account_statement_from_another_agency(): void
    {
        $agenceLocale = $this->creerAgence('AG-RLV-3');
        $agenceAutre = $this->creerAgence('AG-RLV-4');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceLocale);
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.releve.autre@example.test',
            ])
        );

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/comptes/'.$compteAutre->uuid.'/releve?date_debut=2026-10-01&date_fin=2026-10-31')
            ->assertNotFound();
    }

    public function test_migration_reconstructs_existing_account_opening_balance_from_posted_transactions(): void
    {
        $agence = $this->creerAgence('AG-RLV-5');
        $compte = $this->creerCompte(
            $this->creerClient($agence),
            ['solde' => '1000.00']
        );
        $this->creerTransaction($compte, [
            'montant' => '200.00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'montant' => '50.00',
            'compte_source_id' => $compte->id,
        ]);
        $this->creerTransaction($compte, [
            'montant' => '900.00',
            'compte_destinataire_id' => $compte->id,
            'statut_transaction' => 'en_attente',
            'date_validation' => null,
        ]);

        $migration = require database_path(
            'migrations/2026_11_13_151002_add_solde_initial_to_comptes_table.php'
        );
        $migration->down();
        $migration->up();

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '1000.00',
            'solde_initial' => '850.00',
        ]);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerUtilisateur(
        UserRole $role,
        ?Agence $agence = null,
        array $remplacements = []
    ): User {
        return User::factory()->create([
            'role' => $role,
            'agence_id' => $agence?->id,
            ...$remplacements,
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

    /**
     * @param  array<string, mixed>  $remplacements
     * @return array<string, mixed>
     */
    private function donneesClient(array $remplacements = []): array
    {
        return [
            'nom_client' => 'Kouassi',
            'prenom_client' => 'Aminata',
            'adresse_client' => 'Centre-ville',
            'telephone_client' => '0701000000',
            'ville_client' => 'Abidjan',
            'email_client' => 'aminata.client@example.test',
            'numero_piece_identite' => 'CNI-SENSIBLE',
            'revenu_mensuel' => '500000.00',
            ...$remplacements,
        ];
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerClient(Agence $agence, array $remplacements = []): Client
    {
        return Client::query()->create([
            'agence_id' => $agence->id,
            'code_client' => 'CLI-'.strtoupper(fake()->unique()->bothify('??????##')),
            'nom_client' => 'Kouassi',
            'prenom_client' => 'Aminata',
            'adresse_client' => 'Centre-ville',
            'telephone_client' => '0701000000',
            'ville_client' => 'Abidjan',
            'email_client' => 'client.'.strtolower(fake()->unique()->bothify('??????##')).'@example.test',
            'statut' => 'actif',
            ...$remplacements,
        ]);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerCompte(Client $client, array $remplacements = []): Compte
    {
        static $sequence = 0;
        $sequence++;

        return Compte::query()->create([
            'agence_id' => $client->agence_id,
            'client_id' => $client->id,
            'numero_compte' => 'C-TEST-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'type_compte' => 'courant',
            'solde' => '0.00',
            'solde_initial' => '0.00',
            'devise' => 'XOF',
            'date_ouverture' => '2026-01-01',
            'statut_compte' => 'actif',
            ...$remplacements,
        ]);
    }

    /**
     * @param  array<string, mixed>  $remplacements
     */
    private function creerTransaction(Compte $compte, array $remplacements): Transaction
    {
        return Transaction::query()->create([
            'reference_transaction' => 'TRX-'.strtoupper(fake()->unique()->bothify('????????##')),
            'agence_id' => $compte->agence_id,
            'client_id' => $compte->client_id,
            'type_transaction' => 'depot',
            'montant' => '1.00',
            'devise' => 'XOF',
            'date_transaction' => '2026-10-10 08:00:00',
            'statut_transaction' => 'terminee',
            'date_validation' => '2026-10-10 08:05:00',
            ...$remplacements,
        ]);
    }
}
