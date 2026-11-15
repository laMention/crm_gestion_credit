<?php

namespace Tests\Feature\Api\V1;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\EcheanceCredit;
use App\Models\Reporting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_sees_booked_network_flows_without_double_counting_transfers(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $agenceUne = $this->creerAgence('AG-REP-1');
        $agenceDeux = $this->creerAgence('AG-REP-2');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compteUn = $this->creerCompte($this->creerClient($agenceUne));
        $compteDeux = $this->creerCompte(
            $this->creerClient($agenceDeux, 'client.reporting.deux@example.test'),
            'C-REP-000002'
        );

        $this->creerTransaction($compteUn, 'depot', '100.00', 'terminee');
        $this->creerTransaction($compteUn, 'retrait', '30.00', 'terminee');
        $this->creerTransaction($compteUn, 'virement', '25.00', 'terminee');
        $this->creerTransaction($compteUn, 'depot', '999.00', 'en_attente', false);
        $this->creerTransaction($compteUn, 'retrait', '999.00', 'annulee');
        $this->creerTransaction($compteDeux, 'depot', '500.00', 'terminee');

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs?date_debut=2026-11-13&date_fin=2026-11-13')
            ->assertOk()
            ->assertJsonPath('data.indicateurs_generaux.nombre_total_transactions', 4)
            ->assertJsonPath('data.indicateurs_generaux.volume_brut_operations', '655.00')
            ->assertJsonPath('data.indicateurs_generaux.flux_net_hors_virements', '570.00')
            ->assertJsonPath('data.transactions.virement.nombre', 1)
            ->assertJsonPath('data.transactions.virement.montant_total', '25.00');
    }

    public function test_branch_manager_is_scoped_and_cannot_override_the_agency_filter(): void
    {
        $agenceLocale = $this->creerAgence('AG-REP-3');
        $agenceAutre = $this->creerAgence('AG-REP-4');
        $chef = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agenceLocale);
        $compteLocal = $this->creerCompte($this->creerClient($agenceLocale));
        $compteAutre = $this->creerCompte(
            $this->creerClient($agenceAutre, 'client.reporting.autre@example.test'),
            'C-REP-000004'
        );
        $this->creerTransaction($compteLocal, 'depot', '75.00', 'terminee');
        $this->creerTransaction($compteAutre, 'depot', '900.00', 'terminee');

        $this->actingAs($chef, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs')
            ->assertOk()
            ->assertJsonPath('data.indicateurs_generaux.nombre_total_transactions', 1)
            ->assertJsonPath('data.indicateurs_generaux.volume_brut_operations', '75.00')
            ->assertJsonPath(
                'data.perimetre',
                'agence:'.$agenceLocale->id
            );

        $this->getJson('/api/v1/reportings/indicateurs?agence_id='.$agenceAutre->id)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['agence_id']);
    }

    public function test_administrator_can_consult_but_cannot_export_reporting(): void
    {
        $agence = $this->creerAgence('AG-REP-5');
        $administrateur = $this->creerUtilisateur(UserRole::ADMINISTRATEUR);
        $compte = $this->creerCompte($this->creerClient($agence));
        $this->creerTransaction($compte, 'depot', '20.00', 'terminee');

        $this->actingAs($administrateur, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs')
            ->assertOk()
            ->assertJsonPath('data.indicateurs_generaux.volume_brut_operations', '20.00');

        $this->get('/api/v1/reportings/export')->assertForbidden();
    }

    public function test_adviser_cannot_consult_reporting(): void
    {
        $agence = $this->creerAgence('AG-REP-6');
        $conseiller = $this->creerUtilisateur(UserRole::CONSEILLER, $agence);

        $this->actingAs($conseiller, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs')
            ->assertForbidden();
    }

    public function test_daily_report_recalculation_updates_one_snapshot_and_is_audited(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $agence = $this->creerAgence('AG-REP-7');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compte = $this->creerCompte($this->creerClient($agence));
        $this->creerTransaction($compte, 'depot', '125.00', 'terminee');

        $this->actingAs($direction, 'sanctum')
            ->postJson('/api/v1/reportings/generer', [
                'agence_id' => $agence->id,
                'date_reporting' => '2026-11-13',
            ])
            ->assertCreated()
            ->assertJsonPath(
                'data.indicateurs.indicateurs_generaux.volume_brut_operations',
                '125.00'
            );

        $this->postJson('/api/v1/reportings/generer', [
            'agence_id' => $agence->id,
            'date_reporting' => '2026-11-13',
        ])->assertOk();

        $this->assertSame(1, Reporting::query()
            ->where('agence_id', $agence->id)
            ->whereDate('date_reporting', '2026-11-13')
            ->count());
        $this->assertSame(2, AuditLog::query()
            ->where('action', 'reporting.genere')
            ->count());

        $this->getJson(
            '/api/v1/reportings?date_debut=2026-11-13&date_fin=2026-11-13'
        )->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_branch_manager_can_generate_its_own_daily_report(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $agence = $this->creerAgence('AG-REP-10');
        $chef = $this->creerUtilisateur(UserRole::CHEF_AGENCE, $agence);

        $this->actingAs($chef, 'sanctum')
            ->postJson('/api/v1/reportings/generer', [
                'date_reporting' => '2026-11-13',
            ])
            ->assertCreated()
            ->assertJsonPath('data.agence_id', $agence->id);

        $this->assertDatabaseHas('reportings', [
            'agence_id' => $agence->id,
            'date_reporting' => '2026-11-13 00:00:00',
        ]);
    }

    public function test_invoice_payments_are_counted_and_reduce_network_net_flow(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $agence = $this->creerAgence('AG-REP-11');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compte = $this->creerCompte($this->creerClient($agence));
        $this->creerTransaction($compte, 'paiement_facture', '15.25', 'terminee');

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs')
            ->assertOk()
            ->assertJsonPath('data.indicateurs_generaux.nombre_total_transactions', 1)
            ->assertJsonPath('data.indicateurs_generaux.volume_brut_operations', '15.25')
            ->assertJsonPath('data.indicateurs_generaux.flux_net_hors_virements', '-15.25')
            ->assertJsonPath('data.transactions.paiement_facture.nombre', 1);
    }

    public function test_reporting_separates_overdue_credits_past_thirty_days(): void
    {
        $this->travelTo('2026-11-13 12:00:00');
        $agence = $this->creerAgence('AG-REP-12');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compte = $this->creerCompte($this->creerClient($agence));
        $credit = Credit::query()->create([
            'compte_id' => $compte->id,
            'montant_demande' => '1000.00',
            'montant_accorde' => '1000.00',
            'taux_interet_annuel' => '12.0000',
            'duree_mois' => 2,
            'capital_restant_du' => '1000.00',
            'date_demande' => '2026-09-01',
            'date_deboursement' => '2026-09-02',
            'statut' => 'en_remboursement',
        ]);
        EcheanceCredit::query()->create([
            'credit_id' => $credit->id,
            'numero_echeance' => 1,
            'date_echeance' => today()->subDays(31),
            'capital' => '500.00',
            'interet' => '10.00',
            'montant_echeance' => '510.00',
        ]);
        EcheanceCredit::query()->create([
            'credit_id' => $credit->id,
            'numero_echeance' => 2,
            'date_echeance' => today()->subDays(30),
            'capital' => '500.00',
            'interet' => '10.00',
            'montant_echeance' => '510.00',
        ]);

        $this->actingAs($direction, 'sanctum')
            ->getJson('/api/v1/reportings/indicateurs')
            ->assertOk()
            ->assertJsonPath(
                'data.indicateurs_credits.credits_avec_echeances_en_retard',
                1
            )
            ->assertJsonPath(
                'data.indicateurs_credits.credits_avec_echeances_impayees_plus_de_30_jours',
                1
            );
    }

    public function test_direction_export_uses_filters_and_excludes_personal_data(): void
    {
        $agence = $this->creerAgence('AG-REP-8');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compte = $this->creerCompte(
            $this->creerClient($agence, 'personne.non.exportee@example.test')
        );
        $this->creerTransaction($compte, 'depot', '42.50', 'terminee');

        $response = $this->actingAs($direction, 'sanctum')
            ->get('/api/v1/reportings/export?type_transaction=depot')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $contenu = $response->streamedContent();
        $this->assertStringContainsString('42.50', $contenu);
        $this->assertStringNotContainsString('personne.non.exportee@example.test', $contenu);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $direction->id,
            'action' => 'reporting.exporte',
            'resultat' => 'succes',
        ]);
    }

    public function test_date_range_and_transaction_type_filters_are_applied(): void
    {
        $agence = $this->creerAgence('AG-REP-9');
        $direction = $this->creerUtilisateur(UserRole::DIRECTION);
        $compte = $this->creerCompte($this->creerClient($agence));
        $this->creerTransaction($compte, 'depot', '10.00', 'terminee', true, '2026-11-12');
        $this->creerTransaction($compte, 'depot', '20.00', 'terminee');
        $this->creerTransaction($compte, 'retrait', '5.00', 'terminee');

        $this->actingAs($direction, 'sanctum')
            ->getJson(
                '/api/v1/reportings/transactions?date_debut=2026-11-13&date_fin=2026-11-13&type_transaction=depot'
            )
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.montant', '20.00')
            ->assertJsonPath('data.0.type_transaction', 'depot');
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

    private function creerClient(Agence $agence, string $email = 'client.reporting@example.test'): Client
    {
        return Client::query()->create([
            'agence_id' => $agence->id,
            'code_client' => 'CLI-'.strtoupper(fake()->unique()->bothify('??????##')),
            'nom_client' => 'Client',
            'prenom_client' => 'Reporting',
            'adresse_client' => 'Centre-ville',
            'telephone_client' => '0700000000',
            'ville_client' => 'Abidjan',
            'email_client' => $email,
            'statut' => 'actif',
        ]);
    }

    private function creerCompte(Client $client, string $numero = 'C-REP-000001'): Compte
    {
        static $sequence = 0;
        $sequence++;

        return Compte::query()->create([
            'agence_id' => $client->agence_id,
            'client_id' => $client->id,
            'numero_compte' => $numero.'-'.$sequence,
            'type_compte' => 'courant',
            'solde' => '0.00',
            'solde_initial' => '0.00',
            'devise' => 'XOF',
            'date_ouverture' => '2026-01-01',
            'statut_compte' => 'actif',
        ]);
    }

    private function creerTransaction(
        Compte $compte,
        string $type,
        string $montant,
        string $statut,
        bool $dateDansPeriode = true,
        string $date = '2026-11-13'
    ): Transaction {
        static $sequence = 0;
        $sequence++;

        return Transaction::query()->create([
            'reference_transaction' => 'TRX-REP-'.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT),
            'agence_id' => $compte->agence_id,
            'client_id' => $compte->client_id,
            'type_transaction' => $type,
            'montant' => $montant,
            'devise' => 'XOF',
            'date_transaction' => $date.' 10:00:00',
            'compte_source_id' => $type === 'depot' ? null : $compte->id,
            'compte_destinataire_id' => $type === 'retrait' ? null : $compte->id,
            'statut_transaction' => $statut,
            'date_validation' => $dateDansPeriode && $statut === 'terminee'
                ? $date.' 10:01:00'
                : null,
        ]);
    }
}
