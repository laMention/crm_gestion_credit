<?php

namespace Database\Seeders;

use App\Models\Agence;
use Illuminate\Database\Seeder;

class AgenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agences = [
            [
                'code_agence' => 'AG-ABJ-001',
                'nom_agence' => 'Agence Abidjan Plateau',
                'ville_agence' => 'Abidjan',
                'adresse_agence' => 'Plateau, Abidjan',
                'telephone_agence' => '0700000001',
                'email_agence' => 'plateau@crm-finance.test',
                'actif' => true,
            ],
            [
                'code_agence' => 'AG-YAK-002',
                'nom_agence' => 'Agence Yamoussoukro',
                'ville_agence' => 'Yamoussoukro',
                'adresse_agence' => 'Centre-ville, Yamoussoukro',
                'telephone_agence' => '0700000002',
                'email_agence' => 'yamoussoukro@crm-finance.test',
                'actif' => true,
            ],
            [
                'code_agence' => 'AG-BKE-003',
                'nom_agence' => 'Agence Bouaké',
                'ville_agence' => 'Bouaké',
                'adresse_agence' => 'Centre-ville, Bouaké',
                'telephone_agence' => '0700000003',
                'email_agence' => 'bouake@crm-finance.test',
                'actif' => true,
            ],
        ];

        foreach ($agences as $agence) {
            Agence::updateOrCreate(
                ['code_agence' => $agence['code_agence']],
                $agence
            );
        }
    }
}
