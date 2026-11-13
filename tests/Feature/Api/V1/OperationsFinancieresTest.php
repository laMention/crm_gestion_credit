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

class OperationsFinancieresTest extends TestCase
{
    use RefreshDatabase;

    public function test_caissier_deposits_idempotently_without_exposing_metadata(): void
    {
        $agence = $this->creerAgence('AG-DEP-1');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));

        $reponse = $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/depots',
                ['montant' => '125.25', 'motif' => 'Versement'],
                ['Idempotency-Key' => 'depot-unique-001']
            )
            ->assertCreated()
            ->assertJsonPath('data.type_transaction', 'depot')
            ->assertJsonPath('data.montant', '125.25')
            ->assertJsonMissingPath('data.metadata');

        $uuidTransaction = $reponse->json('data.uuid');

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/depots',
            ['montant' => '125.25', 'motif' => 'Versement'],
            ['Idempotency-Key' => 'depot-unique-001']
        )
            ->assertOk()
            ->assertJsonPath('data.uuid', $uuidTransaction);

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '125.25',
        ]);
        $this->assertSame(
            1,
            Transaction::query()->where('cle_idempotence', 'depot-unique-001')->count()
        );
    }

    public function test_same_idempotency_key_with_different_operation_is_rejected(): void
    {
        $agence = $this->creerAgence('AG-DEP-2');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $this->actingAs($caissier, 'sanctum');

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/depots',
            ['montant' => '100'],
            ['Idempotency-Key' => 'depot-conflict-001']
        )->assertCreated();

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/depots',
            ['montant' => '101'],
            ['Idempotency-Key' => 'depot-conflict-001']
        )->assertConflict();

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '100.00',
        ]);
    }

    public function test_only_caissiers_can_deposit_and_foreign_accounts_are_hidden(): void
    {
        $agenceLocale = $this->creerAgence('AG-DEP-3');
        $agenceAutre = $this->creerAgence('AG-DEP-4');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceLocale);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agenceLocale);
        $compteLocal = $this->creerCompte($this->creerClient($agenceLocale));
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.operations.autre@example.test',
            ])
        );

        $this->actingAs($conseiller, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compteLocal->uuid.'/depots',
                ['montant' => '100'],
                ['Idempotency-Key' => 'depot-forbidden-001']
            )
            ->assertForbidden();

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compteAutre->uuid.'/depots',
                ['montant' => '100'],
                ['Idempotency-Key' => 'depot-foreign-001']
            )
            ->assertNotFound();

        $this->assertDatabaseHas('comptes', [
            'id' => $compteLocal->id,
            'solde' => '0.00',
        ]);
    }

    public function test_deposit_on_blocked_account_and_invalid_amount_are_rejected(): void
    {
        $agence = $this->creerAgence('AG-DEP-5');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte(
            $this->creerClient($agence),
            ['statut_compte' => 'bloque']
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/depots',
                ['montant' => '0'],
                ['Idempotency-Key' => 'depot-blocked-001']
            )
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['montant']);

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/depots',
            ['montant' => '100'],
            ['Idempotency-Key' => 'depot-blocked-002']
        )->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_withdrawal_debits_atomically_and_rejects_insufficient_funds_or_blocked_client(): void
    {
        $agence = $this->creerAgence('AG-RET-1');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $client = $this->creerClient($agence);
        $compte = $this->creerCompte($client, ['solde' => '500.50']);
        $this->actingAs($caissier, 'sanctum');

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/retraits',
            ['montant' => '125.25'],
            ['Idempotency-Key' => 'retrait-success-001']
        )
            ->assertCreated()
            ->assertJsonPath('data.statut_transaction', 'terminee');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '375.25',
        ]);

        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/retraits',
            ['montant' => '400'],
            ['Idempotency-Key' => 'retrait-insuffisant-001']
        )->assertUnprocessable();

        $client->forceFill(['statut' => 'bloque'])->save();
        $this->postJson(
            '/api/v1/comptes/'.$compte->uuid.'/retraits',
            ['montant' => '10'],
            ['Idempotency-Key' => 'retrait-client-bloque-001']
        )->assertUnprocessable();

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '375.25',
        ]);
        $this->assertSame(1, Transaction::query()->count());

        $compteBloque = $this->creerCompte(
            $this->creerClient($agence, [
                'email_client' => 'client.retrait.compte-bloque@example.test',
            ]),
            ['solde' => '100.00', 'statut_compte' => 'bloque']
        );
        $this->postJson(
            '/api/v1/comptes/'.$compteBloque->uuid.'/retraits',
            ['montant' => '10'],
            ['Idempotency-Key' => 'retrait-compte-bloque-001']
        )->assertUnprocessable();
        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_withdrawal_of_exactly_two_million_does_not_require_approval(): void
    {
        $agence = $this->creerAgence('AG-RET-4');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte(
            $this->creerClient($agence),
            ['solde' => '2000000.00']
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/retraits',
                ['montant' => '2000000'],
                ['Idempotency-Key' => 'retrait-seuil-exact-001']
            )
            ->assertCreated()
            ->assertJsonPath('data.statut_transaction', 'terminee');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '0.00',
        ]);
    }

    public function test_large_withdrawal_requires_branch_manager_and_cannot_be_self_approved(): void
    {
        $agence = $this->creerAgence('AG-RET-2');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte(
            $this->creerClient($agence),
            ['solde' => '3000000.00']
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/retraits',
                ['montant' => '2000000.01'],
                ['Idempotency-Key' => 'retrait-large-001']
            )
            ->assertAccepted()
            ->assertJsonPath('data.statut_transaction', 'en_attente');

        $transaction = Transaction::query()->firstOrFail();
        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '3000000.00',
        ]);
        $agenceAutre = $this->creerAgence('AG-RET-5');
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.retrait.autre-agence@example.test',
            ]),
            ['solde' => '3000000.00']
        );
        $retraitAutreAgence = $this->creerTransaction($compteAutre, [
            'reference_transaction' => 'TRX-RET-FOREIGN',
            'type_transaction' => 'retrait',
            'montant' => '2100000.00',
            'statut_transaction' => 'en_attente',
            'date_validation' => null,
            'compte_source_id' => $compteAutre->id,
            'compte_destinataire_id' => null,
        ]);

        $this->getJson('/api/v1/transactions/retraits-en-attente')
            ->assertForbidden();

        $this->patchJson('/api/v1/transactions/'.$transaction->uuid.'/approuver')
            ->assertForbidden();

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/transactions/retraits-en-attente')
            ->assertOk()
            ->assertJsonFragment(['uuid' => $transaction->uuid])
            ->assertJsonMissing(['uuid' => $retraitAutreAgence->uuid]);

        $this->patchJson('/api/v1/transactions/'.$transaction->uuid.'/approuver')
            ->assertOk()
            ->assertJsonPath('data.statut_transaction', 'terminee');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '999999.99',
        ]);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'valide_par' => $chefAgence->id,
        ]);
    }

    public function test_branch_manager_can_reject_pending_withdrawal_without_changing_balance(): void
    {
        $agence = $this->creerAgence('AG-RET-3');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte(
            $this->creerClient($agence),
            ['solde' => '2500000.00']
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/retraits',
                ['montant' => '2100000'],
                ['Idempotency-Key' => 'retrait-rejet-001']
            )
            ->assertAccepted();

        $transaction = Transaction::query()->firstOrFail();
        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/transactions/'.$transaction->uuid.'/rejeter', [
                'motif' => 'Justificatif manquant',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut_transaction', 'annulee')
            ->assertJsonPath('data.motif', 'Motif du rejet : Justificatif manquant');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '2500000.00',
        ]);
    }

    public function test_transfer_debits_and_credits_and_rejects_same_or_foreign_account(): void
    {
        $agence = $this->creerAgence('AG-VIR-1');
        $autreAgence = $this->creerAgence('AG-VIR-2');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compteSource = $this->creerCompte(
            $this->creerClient($agence),
            ['solde' => '1000.00']
        );
        $compteDestinataire = $this->creerCompte(
            $this->creerClient($agence, [
                'email_client' => 'client.virement2@example.test',
            ]),
            ['solde' => '20.00']
        );
        $compteAutreAgence = $this->creerCompte(
            $this->creerClient($autreAgence, [
                'email_client' => 'client.virement3@example.test',
            ])
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/comptes/'.$compteSource->uuid.'/virements',
                [
                    'compte_destinataire_uuid' => $compteDestinataire->uuid,
                    'montant' => '125.25',
                ],
                ['Idempotency-Key' => 'virement-success-001']
            )
            ->assertCreated()
            ->assertJsonPath('data.type_transaction', 'virement');

        $this->postJson(
            '/api/v1/comptes/'.$compteSource->uuid.'/virements',
            [
                'compte_destinataire_uuid' => $compteDestinataire->uuid,
                'montant' => '125.25',
            ],
            ['Idempotency-Key' => 'virement-success-001']
        )->assertOk();

        $this->assertDatabaseHas('comptes', [
            'id' => $compteSource->id,
            'solde' => '874.75',
        ]);
        $this->assertDatabaseHas('comptes', [
            'id' => $compteDestinataire->id,
            'solde' => '145.25',
        ]);

        $this->postJson(
            '/api/v1/comptes/'.$compteSource->uuid.'/virements',
            [
                'compte_destinataire_uuid' => $compteDestinataire->uuid,
                'montant' => '900',
            ],
            ['Idempotency-Key' => 'virement-insuffisant-001']
        )->assertUnprocessable();

        $this->postJson(
            '/api/v1/comptes/'.$compteSource->uuid.'/virements',
            [
                'compte_destinataire_uuid' => $compteSource->uuid,
                'montant' => '10',
            ],
            ['Idempotency-Key' => 'virement-same-account-001']
        )->assertUnprocessable();

        $this->postJson(
            '/api/v1/comptes/'.$compteSource->uuid.'/virements',
            [
                'compte_destinataire_uuid' => $compteAutreAgence->uuid,
                'montant' => '10',
            ],
            ['Idempotency-Key' => 'virement-foreign-001']
        )->assertNotFound();

        $compteAutreDevise = $this->creerCompte(
            $this->creerClient($agence, [
                'email_client' => 'client.virement4@example.test',
            ]),
            ['devise' => 'EUR']
        );
        $this->postJson(
            '/api/v1/comptes/'.$compteSource->uuid.'/virements',
            [
                'compte_destinataire_uuid' => $compteAutreDevise->uuid,
                'montant' => '10',
            ],
            ['Idempotency-Key' => 'virement-devise-001']
        )->assertUnprocessable();

        $this->assertSame(1, Transaction::query()->count());
    }

    public function test_transaction_history_is_paginated_and_hidden_from_other_agencies(): void
    {
        $agenceLocale = $this->creerAgence('AG-HIS-1');
        $agenceAutre = $this->creerAgence('AG-HIS-2');
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agenceLocale);
        $compteLocal = $this->creerCompte($this->creerClient($agenceLocale));
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.historique.autre@example.test',
            ])
        );
        $transaction = $this->creerTransaction($compteAutre, [
            'reference_transaction' => 'TRX-HIST-FOREIGN',
        ]);
        $transactionLocale = $this->creerTransaction($compteLocal, [
            'reference_transaction' => 'TRX-HIST-LOCAL',
        ]);

        $this->actingAs($caissier, 'sanctum')
            ->getJson('/api/v1/transactions?per_page=1')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonFragment(['uuid' => $transactionLocale->uuid])
            ->assertJsonMissing(['uuid' => $transaction->uuid])
            ->assertJsonMissingPath('data.0.metadata');

        $this->getJson('/api/v1/transactions/'.$transaction->uuid)
            ->assertNotFound();
    }

    public function test_network_roles_can_consult_all_transactions_but_cannot_perform_deposits(): void
    {
        $agence = $this->creerAgence('AG-HIS-3');
        $compte = $this->creerCompte($this->creerClient($agence));
        $transaction = $this->creerTransaction($compte, [
            'reference_transaction' => 'TRX-HIST-NETWORK',
        ]);

        foreach ([UserRole::DIRECTION, UserRole::ADMINISTRATEUR] as $role) {
            $utilisateur = $this->creerUtilisateur($role);

            $this->actingAs($utilisateur, 'sanctum')
                ->getJson('/api/v1/transactions/'.$transaction->uuid)
                ->assertOk();

            $this->postJson(
                '/api/v1/comptes/'.$compte->uuid.'/depots',
                ['montant' => '10'],
                ['Idempotency-Key' => 'depot-'.$role->value]
            )->assertForbidden();
        }
    }

    public function test_pending_withdrawal_permission_is_listed_for_branch_managers(): void
    {
        $agence = $this->creerAgence('AG-PER-1');
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);

        $this->actingAs($chefAgence, 'sanctum')
            ->getJson('/api/v1/roles/chef_agence')
            ->assertOk()
            ->assertJsonFragment([
                'id' => 'transactions.consulter_retraits_en_attente',
                'identifiant' => 'consulterRetraitsEnAttente',
            ]);
    }

    private function creerUtilisateur(UserRole $role, ?Agence $agence = null): User
    {
        return User::factory()->create([
            'role' => $role,
            'agence_id' => $agence?->id,
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
            'numero_compte' => 'C-OP-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
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
    private function creerTransaction(
        Compte $compte,
        array $remplacements = []
    ): Transaction {
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
            'compte_destinataire_id' => $compte->id,
            ...$remplacements,
        ]);
    }
}
