<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionCreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conseiller_creates_and_submits_a_credit_request(): void
    {
        $agence = $this->creerAgence('AG-CRE-1');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/comptes/'.$compte->uuid.'/credits', [
                'montant_demande' => '1000.00',
                'taux_interet_annuel' => '0',
                'duree_mois' => 3,
                'observations' => 'Financement équipement',
                'agence_id' => $agence->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agence_id']);

        $reponse = $this->postJson('/api/v1/comptes/'.$compte->uuid.'/credits', [
            'montant_demande' => '1000.00',
            'taux_interet_annuel' => '0',
            'duree_mois' => 3,
            'observations' => 'Financement équipement',
        ])
            ->assertCreated()
            ->assertJsonPath('data.statut', 'en_etude')
            ->assertJsonPath('data.montant_demande', '1000.00');

        $creditUuid = $reponse->json('data.uuid');
        $credit = Credit::query()->where('uuid', $creditUuid)->firstOrFail();
        $this->assertSame($conseiller->id, $credit->conseiller_client_id);
        $this->assertSame($compte->id, $credit->compte_id);

        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$creditUuid.'/approuver')
            ->assertForbidden();

        $this->actingAs($conseiller, 'sanctum')
            ->patchJson('/api/v1/credits/'.$creditUuid, [
                'montant_demande' => '1100.00',
            ])
            ->assertOk()
            ->assertJsonPath('data.montant_demande', '1100.00');

        $this->postJson('/api/v1/credits/'.$creditUuid.'/soumettre')
            ->assertOk()
            ->assertJsonPath('data.statut', 'en_attente');

        $this->patchJson('/api/v1/credits/'.$creditUuid, [
            'montant_demande' => '1200.00',
        ])->assertForbidden();

        $this->assertDatabaseHas('credits', [
            'id' => $credit->id,
            'statut' => 'en_attente',
            'montant_demande' => '1100.00',
        ]);
    }

    public function test_only_a_local_adviser_can_create_a_request_for_an_active_client(): void
    {
        $agenceLocale = $this->creerAgence('AG-CRE-2');
        $agenceAutre = $this->creerAgence('AG-CRE-3');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agenceLocale);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agenceLocale);
        $compteLocal = $this->creerCompte($this->creerClient($agenceLocale));
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, [
                'email_client' => 'client.credit.autre@example.test',
            ]),
            ['numero_compte' => 'C-CREDIT-OTHER']
        );

        $this->actingAs($caissier, 'sanctum')
            ->postJson('/api/v1/comptes/'.$compteLocal->uuid.'/credits', [
                'montant_demande' => '1000.00',
                'taux_interet_annuel' => '0',
                'duree_mois' => 3,
            ])
            ->assertForbidden();

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/comptes/'.$compteAutre->uuid.'/credits', [
                'montant_demande' => '1000.00',
                'taux_interet_annuel' => '0',
                'duree_mois' => 3,
            ])
            ->assertNotFound();

        $this->assertDatabaseCount('credits', 0);
    }

    public function test_approval_disburses_once_and_generates_an_exact_schedule(): void
    {
        $agence = $this->creerAgence('AG-CRE-4');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk()
            ->assertJsonPath('data.statut', 'debourse')
            ->assertJsonPath('data.montant_accorde', '1000.00');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '1000.00',
        ]);
        $this->assertDatabaseHas('transactions', [
            'type_transaction' => 'deboursement',
            'montant' => '1000.00',
            'compte_destinataire_id' => $compte->id,
        ]);
        $this->assertSame(3, EcheanceCredit::query()
            ->where('credit_id', $credit->id)
            ->count());
        $this->assertSame(
            '1000.00',
            number_format(
                (float) EcheanceCredit::query()
                    ->where('credit_id', $credit->id)
                    ->sum('montant_echeance'),
                2,
                '.',
                ''
            )
        );
        $this->assertDatabaseHas('echeance_credits', [
            'credit_id' => $credit->id,
            'numero_echeance' => 3,
            'montant_echeance' => '333.34',
        ]);

        $this->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertForbidden();
        $this->assertSame(1, Transaction::query()
            ->where('type_transaction', 'deboursement')
            ->count());
    }

    public function test_adviser_cannot_approve_and_manager_cannot_decide_for_another_branch(): void
    {
        $agence = $this->creerAgence('AG-CRE-5');
        $autreAgence = $this->creerAgence('AG-CRE-6');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAutreAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $autreAgence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($conseiller, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertForbidden();

        $this->actingAs($chefAutreAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertNotFound();

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '0.00',
        ]);
    }

    public function test_rejected_credit_is_not_disbursed(): void
    {
        $agence = $this->creerAgence('AG-CRE-7');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/rejeter', [
                'motif_rejet' => 'Dossier incomplet',
            ])
            ->assertOk()
            ->assertJsonPath('data.statut', 'rejete')
            ->assertJsonPath('data.motif_rejet', 'Dossier incomplet');

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '0.00',
        ]);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_cashier_repayment_is_idempotent_and_updates_the_installment(): void
    {
        $agence = $this->creerAgence('AG-CRE-8');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk();

        $payload = [
            'compte_uuid' => $compte->uuid,
            'montant' => '333.33',
        ];
        $this->actingAs($conseiller, 'sanctum')
            ->postJson(
                '/api/v1/credits/'.$credit->uuid.'/remboursements',
                $payload,
                ['Idempotency-Key' => 'credit-remboursement-forbidden-001']
            )
            ->assertForbidden();

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/credits/'.$credit->uuid.'/remboursements',
                $payload,
                ['Idempotency-Key' => 'credit-remboursement-unique-001']
            )
            ->assertCreated()
            ->assertJsonPath('data.transaction.type_transaction', 'remboursement')
            ->assertJsonPath('data.echeances.0.montant_affecte', '333.33');

        $this->postJson(
            '/api/v1/credits/'.$credit->uuid.'/remboursements',
            $payload,
            ['Idempotency-Key' => 'credit-remboursement-unique-001']
        )->assertOk();

        $this->postJson(
            '/api/v1/credits/'.$credit->uuid.'/remboursements',
            ['compte_uuid' => $compte->uuid, 'montant' => '333.34'],
            ['Idempotency-Key' => 'credit-remboursement-unique-001']
        )->assertConflict();

        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '666.67',
        ]);
        $this->assertDatabaseHas('echeance_credits', [
            'credit_id' => $credit->id,
            'numero_echeance' => 1,
            'montant_paye' => '333.33',
            'statut_echeance_credit' => 'paye',
        ]);
        $this->assertSame(1, Transaction::query()
            ->where('type_transaction', 'remboursement')
            ->count());
    }

    public function test_installment_endpoints_are_scoped_and_filtered(): void
    {
        $agence = $this->creerAgence('AG-CRE-9');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk();

        $this->getJson('/api/v1/echeances?credit_uuid='.$credit->uuid)
            ->assertOk()
            ->assertJsonPath('data.0.montant_echeance', '333.33');

        $echeance = EcheanceCredit::query()
            ->where('credit_id', $credit->id)
            ->firstOrFail();
        $this->getJson('/api/v1/echeances/'.$echeance->uuid)
            ->assertOk()
            ->assertJsonPath('data.uuid', $echeance->uuid);
    }

    public function test_repayment_cannot_exceed_the_due_amount_or_available_balance(): void
    {
        $agence = $this->creerAgence('AG-CRE-10');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk();

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/credits/'.$credit->uuid.'/remboursements',
                ['compte_uuid' => $compte->uuid, 'montant' => '1000.01'],
                ['Idempotency-Key' => 'credit-remboursement-overdue-001']
            )
            ->assertUnprocessable();

        $compte->refresh()->forceFill(['solde' => '0.00'])->save();
        $this->postJson(
            '/api/v1/credits/'.$credit->uuid.'/remboursements',
            ['compte_uuid' => $compte->uuid, 'montant' => '100.00'],
            ['Idempotency-Key' => 'credit-remboursement-insufficient-001']
        )->assertUnprocessable();

        $this->assertDatabaseCount('transactions', 1);
        $this->assertDatabaseHas('credits', [
            'id' => $credit->id,
            'statut' => 'debourse',
        ]);
    }

    public function test_full_repayment_marks_the_credit_as_settled(): void
    {
        $agence = $this->creerAgence('AG-CRE-11');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $caissier = $this->creerUtilisateur(UserRole::CAISSIER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk();

        $this->actingAs($caissier, 'sanctum')
            ->postJson(
                '/api/v1/credits/'.$credit->uuid.'/remboursements',
                ['compte_uuid' => $compte->uuid, 'montant' => '1000.00'],
                ['Idempotency-Key' => 'credit-remboursement-full-001']
            )
            ->assertCreated();

        $this->assertDatabaseHas('credits', [
            'id' => $credit->id,
            'statut' => 'solde',
            'capital_restant_du' => '0.00',
        ]);
        $this->assertDatabaseHas('comptes', [
            'id' => $compte->id,
            'solde' => '0.00',
        ]);
        $this->assertSame(3, EcheanceCredit::query()
            ->where('credit_id', $credit->id)
            ->where('statut_echeance_credit', 'paye')
            ->count());
    }

    public function test_credit_request_duration_is_limited_to_six_hundred_months(): void
    {
        $agence = $this->creerAgence('AG-CRE-12');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));

        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/comptes/'.$compte->uuid.'/credits', [
                'montant_demande' => '1000.00',
                'taux_interet_annuel' => '0',
                'duree_mois' => 601,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duree_mois']);

        $this->assertDatabaseCount('credits', 0);
    }

    public function test_tracking_marks_overdue_installments_and_credits(): void
    {
        $agence = $this->creerAgence('AG-CRE-13');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);
        $chefAgence = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = $this->creerDemandeSoumise($conseiller, $compte);

        $this->actingAs($chefAgence, 'sanctum')
            ->patchJson('/api/v1/credits/'.$credit->uuid.'/approuver')
            ->assertOk();

        $echeance = EcheanceCredit::query()
            ->where('credit_id', $credit->id)
            ->firstOrFail();
        $echeance->forceFill([
            'date_echeance' => today()->subDays(31),
        ])->save();

        $this->getJson('/api/v1/credits?statut=en_retard')
            ->assertOk()
            ->assertJsonPath('data.0.statut', 'en_retard');

        $this->getJson('/api/v1/credits/suivi')
            ->assertOk()
            ->assertJsonPath('data.credits_en_retard', 1)
            ->assertJsonPath('data.credits_impayes_plus_de_30_jours', 1);
    }

    private function creerDemandeSoumise(User $conseiller, Compte $compte): Credit
    {
        $this->actingAs($conseiller, 'sanctum')
            ->postJson('/api/v1/comptes/'.$compte->uuid.'/credits', [
                'montant_demande' => '1000.00',
                'taux_interet_annuel' => '0',
                'duree_mois' => 3,
            ])
            ->assertCreated();

        $credit = Credit::query()->where('compte_id', $compte->id)->firstOrFail();
        $this->postJson('/api/v1/credits/'.$credit->uuid.'/soumettre')->assertOk();

        return $credit;
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
            'numero_compte' => 'C-CREDIT-'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
            'type_compte' => 'courant',
            'solde' => '0.00',
            'solde_initial' => '0.00',
            'devise' => 'XOF',
            'date_ouverture' => '2026-01-01',
            'statut_compte' => 'actif',
            ...$remplacements,
        ]);
    }
}
