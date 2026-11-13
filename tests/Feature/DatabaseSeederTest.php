<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_seeders_create_consistent_and_repeatable_data(): void
    {
        $this->seed();
        $this->seed();

        $this->assertDatabaseCount('agences', 3);
        $this->assertDatabaseCount('users', 30);
        $this->assertDatabaseCount('clients', 6);
        $this->assertDatabaseCount('comptes', 18);
        $this->assertDatabaseCount('credits', 6);
        $this->assertDatabaseCount('echeance_credits', 72);
        $this->assertDatabaseCount('transactions', 39);
        $this->assertDatabaseCount('reportings', 3);
        $this->assertDatabaseCount('audit_logs', 6);

        foreach ([
            'administrateur',
            'direction',
            'chef_agence',
            'conseiller',
            'caissier',
        ] as $role) {
            $this->assertSame(
                6,
                User::query()->where('role', $role)->count(),
                "Expected two demo users per agency with the {$role} role."
            );
        }

        $this->assertDatabaseHas('echeance_credits', [
            'statut_echeance_credit' => 'en_retard',
        ]);
        $this->assertDatabaseHas('echeance_credits', [
            'statut_echeance_credit' => 'partiellement_paye',
        ]);
        $this->assertDatabaseHas('transactions', [
            'type_transaction' => 'depot',
            'statut_transaction' => 'terminee',
        ]);
    }
}
