<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $clients = [
            [
                'nom_client' => 'Kouassi',
                'prenom_client' => 'Aminata',
                'sexe' => 'F',
                'profession' => 'Commercante',
                'revenu_mensuel' => 450000,
            ],
            [
                'nom_client' => 'Yao',
                'prenom_client' => 'Koffi',
                'sexe' => 'M',
                'profession' => 'Enseignant',
                'revenu_mensuel' => 380000,
            ],
        ];

        foreach (Agence::query()->orderBy('code_agence')->get() as $agence) {
            $createur = User::query()
                ->where('agence_id', $agence->id)
                ->where('role', 'conseiller_client')
                ->orderBy('email')
                ->firstOrFail();

            foreach ($clients as $index => $client) {
                $sequence = $index + 1;
                $codeClient = sprintf(
                    'CLI-%s-%03d',
                    $agence->code_agence,
                    $sequence
                );

                Client::updateOrCreate(
                    ['code_client' => $codeClient],
                    [
                        'agence_id' => $agence->id,
                        'nom_client' => $client['nom_client'],
                        'prenom_client' => $client['prenom_client'],
                        'adresse_client' => sprintf(
                            'Quartier central, %s',
                            $agence->ville_agence
                        ),
                        'telephone_client' => sprintf(
                            '070%07d',
                            100 + ($agence->id * 10) + $sequence
                        ),
                        'ville_client' => $agence->ville_agence,
                        'email_client' => sprintf(
                            'client.%s.%02d@crm-finance.test',
                            strtolower($agence->code_agence),
                            $sequence
                        ),
                        'date_naissance' => today()->subYears(30 + $sequence),
                        'lieu_naissance' => $agence->ville_agence,
                        'sexe' => $client['sexe'],
                        'type_piece_identite' => 'CNI',
                        'numero_piece_identite' => sprintf(
                            'CNI-%s-%03d',
                            $agence->code_agence,
                            $sequence
                        ),
                        'date_expiration_piece' => today()->addYears(5),
                        'profession' => $client['profession'],
                        'revenu_mensuel' => $client['revenu_mensuel'],
                        'statut' => 'actif',
                        'cree_par_user_id' => $createur->id,
                    ]
                );
            }
        }
    }
}
