<?php

namespace Tests\Feature\Policies;

use App\Enums\UserRole;
use App\Models\Agence;
use App\Models\Client;
use App\Models\Compte;
use App\Models\Credit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationPoliciesTest extends TestCase
{
    use RefreshDatabase;

    public function test_direction_can_consult_network_data_but_cannot_modify_it(): void
    {
        $direction = User::factory()->create([
            'role' => UserRole::DIRECTION,
            'agence_id' => null,
        ]);
        $client = new Client(['agence_id' => 42]);

        $this->assertTrue(Gate::forUser($direction)->allows('consulter', $client));
        $this->assertFalse(Gate::forUser($direction)->allows('modifier', $client));
        $this->assertTrue(Gate::forUser($direction)->allows('consulterTout', Client::class));
    }

    public function test_administrateur_cannot_perform_financial_operations(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);

        $this->assertFalse(
            Gate::forUser($administrateur)->allows(
                'effectuerDepot',
                [Compte::class, new Compte(['agence_id' => 42])]
            )
        );
        $this->assertFalse(
            Gate::forUser($administrateur)->allows(
                'effectuerRetrait',
                [Compte::class, new Compte(['agence_id' => 42])]
            )
        );
        $this->assertFalse(
            Gate::forUser($administrateur)->allows(
                'approuver',
                new Credit
            )
        );
    }

    public function test_conseiller_can_prepare_but_cannot_approve_a_credit(): void
    {
        $agence = $this->creerAgence('AG-POL-1');
        $conseiller = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agence->id,
        ]);
        $credit = new Credit([
            'conseiller_client_id' => $conseiller->id,
            'statut' => 'en_etude',
        ]);
        $credit->setRelation('compte', new Compte([
            'agence_id' => $agence->id,
        ]));

        $this->assertTrue(
            Gate::forUser($conseiller)->allows(
                'preparerDemande',
                [Credit::class, new Compte(['agence_id' => $agence->id])]
            )
        );
        $this->assertFalse(Gate::forUser($conseiller)->allows('approuver', $credit));
    }

    public function test_cashier_cannot_validate_own_large_withdrawal(): void
    {
        $agence = $this->creerAgence('AG-POL-2');
        $caissier = User::factory()->create([
            'role' => UserRole::CAISSIER,
            'agence_id' => $agence->id,
        ]);
        $retrait = new Transaction([
            'agence_id' => $agence->id,
            'type_transaction' => 'retrait',
            'montant' => '2000001.00',
            'devise' => 'XOF',
            'statut_transaction' => 'en_attente',
            'user_id' => $caissier->id,
        ]);

        $this->assertFalse(
            Gate::forUser($caissier)->allows('validerRetrait', $retrait)
        );
    }

    public function test_branch_manager_can_validate_only_another_users_large_pending_withdrawal(): void
    {
        $agence = $this->creerAgence('AG-POL-5');
        $chefAgence = User::factory()->create([
            'role' => UserRole::CHEF_AGENCE,
            'agence_id' => $agence->id,
        ]);
        $caissier = User::factory()->create([
            'role' => UserRole::CAISSIER,
            'agence_id' => $agence->id,
        ]);
        $retrait = new Transaction([
            'agence_id' => $agence->id,
            'type_transaction' => 'retrait',
            'montant' => '2000001.00',
            'devise' => 'XOF',
            'statut_transaction' => 'en_attente',
            'user_id' => $caissier->id,
        ]);
        $retraitAuSeuil = new Transaction([
            'agence_id' => $agence->id,
            'type_transaction' => 'retrait',
            'montant' => '2000000.00',
            'devise' => 'XOF',
            'statut_transaction' => 'en_attente',
            'user_id' => $caissier->id,
        ]);

        $this->assertTrue(
            Gate::forUser($chefAgence)->allows('validerRetrait', $retrait)
        );
        $this->assertFalse(
            Gate::forUser($chefAgence)->allows('validerRetrait', $retraitAuSeuil)
        );
    }

    public function test_branch_manager_cannot_approve_their_own_credit_request(): void
    {
        $agence = $this->creerAgence('AG-POL-6');
        $chefAgence = User::factory()->create([
            'role' => UserRole::CHEF_AGENCE,
            'agence_id' => $agence->id,
        ]);
        $credit = new Credit([
            'conseiller_client_id' => $chefAgence->id,
            'statut' => 'en_etude',
        ]);
        $credit->setRelation('compte', new Compte([
            'agence_id' => $agence->id,
        ]));

        $this->assertFalse(
            Gate::forUser($chefAgence)->allows('approuver', $credit)
        );
    }

    public function test_cross_agency_resource_is_denied_as_not_found(): void
    {
        $agenceLocale = $this->creerAgence('AG-POL-3');
        $conseiller = User::factory()->create([
            'role' => UserRole::CONSEILLER,
            'agence_id' => $agenceLocale->id,
        ]);
        $clientAutreAgence = new Client(['agence_id' => $agenceLocale->id + 1]);

        $decision = Gate::forUser($conseiller)->inspect(
            'consulter',
            $clientAutreAgence
        );

        $this->assertTrue($decision->denied());
        $this->assertSame(404, $decision->status());
    }

    public function test_virement_requires_both_accounts_to_belong_to_the_cashiers_agency(): void
    {
        $agence = $this->creerAgence('AG-POL-4');
        $caissier = User::factory()->create([
            'role' => UserRole::CAISSIER,
            'agence_id' => $agence->id,
        ]);
        $compteSource = new Compte(['agence_id' => $agence->id]);
        $compteDestinataire = new Compte(['agence_id' => $agence->id + 1]);

        $this->assertFalse(
            Gate::forUser($caissier)->allows(
                'effectuerVirementAssiste',
                [Compte::class, $compteSource, $compteDestinataire]
            )
        );
    }

    public function test_policy_abilities_are_registered_with_french_names(): void
    {
        $administrateur = User::factory()->create([
            'role' => UserRole::ADMINISTRATEUR,
            'agence_id' => null,
        ]);

        $this->assertTrue(
            Gate::forUser($administrateur)->allows('creer', Agence::class)
        );
        $this->assertFalse(
            Gate::forUser($administrateur)->allows(
                'effectuerDepot',
                [Compte::class, new Compte(['agence_id' => 42])]
            )
        );
    }

    public function test_role_values_match_the_persisted_contract(): void
    {
        $this->assertSame([
            'administrateur',
            'direction',
            'chef_agence',
            'conseiller',
            'caissier',
        ], array_map(
            fn (UserRole $role): string => $role->value,
            UserRole::cases()
        ));
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
