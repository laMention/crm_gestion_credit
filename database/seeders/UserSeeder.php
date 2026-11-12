<?php

namespace Database\Seeders;

use App\Models\Agence;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'administrateur' => 'Administrateur',
            'direction' => 'Direction',
            'chef_agence' => 'Chef agence',
            'conseiller_client' => 'Conseiller client',
            'caissier' => 'Caissier',
        ];
        $password = Hash::make('Demo123!');
        $userNumber = 0;

        foreach (Agence::query()->orderBy('code_agence')->get() as $agence) {
            foreach ($roles as $role => $nom) {
                for ($index = 1; $index <= 2; $index++) {
                    $userNumber++;
                    $email = sprintf(
                        '%s.%s.%02d@crm-finance.test',
                        $role,
                        strtolower($agence->code_agence),
                        $index
                    );

                    User::updateOrCreate(
                        ['email' => $email],
                        [
                            'agence_id' => $agence->id,
                            'nom' => $nom,
                            'prenom' => sprintf('Demo %02d', $index),
                            'adresse' => $agence->adresse_agence,
                            'telephone' => sprintf('070%07d', $userNumber),
                            'ville' => $agence->ville_agence,
                            'password' => $password,
                            'role' => $role,
                            'actif' => true,
                            'email_verified_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
