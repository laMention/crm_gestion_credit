<?php

namespace Tests\Feature\Services\Authorization;

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

class AccesAgenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_agency_scopes_filter_all_supported_resources(): void
    {
        $agenceLocale = $this->creerAgence('AG-SCOPE-1');
        $agenceAutre = $this->creerAgence('AG-SCOPE-2');
        $conseillerLocal = $this->creerUtilisateur(
            $agenceLocale,
            UserRole::CONSEILLER
        );
        $conseillerAutre = $this->creerUtilisateur(
            $agenceAutre,
            UserRole::CONSEILLER
        );
        $clientLocal = $this->creerClient($agenceLocale, 'CLI-LOCAL');
        $clientAutre = $this->creerClient($agenceAutre, 'CLI-AUTRE');
        $compteLocal = $this->creerCompte(
            $agenceLocale,
            $clientLocal,
            'CPT-LOCAL'
        );
        $compteAutre = $this->creerCompte(
            $agenceAutre,
            $clientAutre,
            'CPT-AUTRE'
        );
        $creditLocal = $this->creerCredit($compteLocal);
        $creditAutre = $this->creerCredit($compteAutre);
        $echeanceLocale = $this->creerEcheance($creditLocal);
        $echeanceAutre = $this->creerEcheance($creditAutre);
        $transactionLocale = $this->creerTransaction($agenceLocale, $clientLocal);
        $transactionAutre = $this->creerTransaction($agenceAutre, $clientAutre);
        $reportingLocal = $this->creerReporting($agenceLocale);
        $reportingAutre = $this->creerReporting($agenceAutre);
        $journalLocal = $this->creerJournal($conseillerLocal);
        $journalAutre = $this->creerJournal($conseillerAutre);

        $this->assertSame(
            [$agenceLocale->id],
            Agence::query()->accessiblePar($conseillerLocal)->pluck('id')->all()
        );
        $this->assertSame(
            [$conseillerLocal->uuid],
            User::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$clientLocal->uuid],
            Client::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$compteLocal->uuid],
            Compte::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$transactionLocale->uuid],
            Transaction::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$creditLocal->uuid],
            Credit::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$echeanceLocale->uuid],
            EcheanceCredit::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$reportingLocal->uuid],
            Reporting::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertSame(
            [$journalLocal->uuid],
            AuditLog::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $clientAutre->uuid,
            Client::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNull(
            Client::query()
                ->accessiblePar($conseillerLocal)
                ->where('uuid', $clientAutre->uuid)
                ->first()
        );
        $this->assertNotContains(
            $compteAutre->uuid,
            Compte::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $transactionAutre->uuid,
            Transaction::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $creditAutre->uuid,
            Credit::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $echeanceAutre->uuid,
            EcheanceCredit::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $reportingAutre->uuid,
            Reporting::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
        $this->assertNotContains(
            $journalAutre->uuid,
            AuditLog::query()->accessiblePar($conseillerLocal)->pluck('uuid')->all()
        );
    }

    public function test_network_roles_keep_network_scope_and_unassigned_staff_get_empty_scope(): void
    {
        $agence = $this->creerAgence('AG-SCOPE-3');
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);
        $conseillerSansAgence = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => null,
        ]);
        $client = $this->creerClient($agence, 'CLI-RESEAU');

        $this->assertSame(
            1,
            Client::query()->accessiblePar($administrateur)->count()
        );
        $this->assertSame(
            0,
            Client::query()->accessiblePar($conseillerSansAgence)->count()
        );
        $this->assertSame(
            0,
            Client::query()
                ->where('uuid', $client->uuid)
                ->accessiblePar($conseillerSansAgence)
                ->count()
        );
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

    private function creerUtilisateur(Agence $agence, UserRole $role): User
    {
        return User::factory()->create([
            'agence_id' => $agence->id,
            'role' => $role,
        ]);
    }

    private function creerClient(Agence $agence, string $code): Client
    {
        return Client::query()->create([
            'agence_id' => $agence->id,
            'code_client' => $code,
            'nom_client' => 'Kouassi',
            'prenom_client' => $code,
            'adresse_client' => 'Centre-ville',
            'telephone_client' => '0700000000',
            'ville_client' => 'Abidjan',
            'email_client' => strtolower($code).'@example.test',
        ]);
    }

    private function creerCompte(
        Agence $agence,
        Client $client,
        string $numero
    ): Compte {
        return Compte::query()->create([
            'agence_id' => $agence->id,
            'client_id' => $client->id,
            'numero_compte' => $numero,
            'type_compte' => 'courant',
            'date_ouverture' => today(),
        ]);
    }

    private function creerCredit(Compte $compte): Credit
    {
        return Credit::query()->create([
            'compte_id' => $compte->id,
            'montant_demande' => 100000,
            'taux_interet_annuel' => 12,
            'duree_mois' => 12,
            'date_demande' => today(),
        ]);
    }

    private function creerEcheance(Credit $credit): EcheanceCredit
    {
        return EcheanceCredit::query()->create([
            'credit_id' => $credit->id,
            'numero_echeance' => 1,
            'date_echeance' => today(),
            'capital' => 10000,
            'interet' => 1000,
            'montant_echeance' => 11000,
        ]);
    }

    private function creerTransaction(Agence $agence, Client $client): Transaction
    {
        static $numero = 0;
        $numero++;

        return Transaction::query()->create([
            'reference_transaction' => 'TRX-'.$numero,
            'agence_id' => $agence->id,
            'client_id' => $client->id,
            'type_transaction' => 'depot',
            'montant' => 10000,
            'statut_transaction' => 'terminee',
        ]);
    }

    private function creerReporting(Agence $agence): Reporting
    {
        static $numero = 0;
        $numero++;

        return Reporting::query()->create([
            'agence_id' => $agence->id,
            'date_reporting' => today()->addDays($numero),
        ]);
    }

    private function creerJournal(User $user): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $user->id,
            'action' => 'consultation',
        ]);
    }
}
