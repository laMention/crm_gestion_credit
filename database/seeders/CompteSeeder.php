<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Compte;
use Illuminate\Database\Seeder;

class CompteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            'courant' => ['code' => 'C', 'solde' => 350000],
            'epargne' => ['code' => 'E', 'solde' => 750000],
            'credit' => ['code' => 'K', 'solde' => 1200000],
        ];

        foreach (Client::query()->orderBy('code_client')->get() as $client) {
            foreach ($types as $type => $details) {
                Compte::updateOrCreate(
                    [
                        'numero_compte' => sprintf(
                            'C-%s-%s',
                            $client->code_client,
                            $details['code']
                        ),
                    ],
                    [
                        'agence_id' => $client->agence_id,
                        'client_id' => $client->id,
                        'type_compte' => $type,
                        'solde' => $details['solde'],
                        'solde_initial' => $details['solde'],
                        'devise' => 'XOF',
                        'date_ouverture' => today()->subMonths(6),
                        'statut_compte' => 'actif',
                        'cree_par_user_id' => $client->cree_par_user_id,
                    ]
                );
            }
        }
    }
}
