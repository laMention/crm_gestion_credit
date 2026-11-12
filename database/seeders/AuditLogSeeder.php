<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Client::query()->orderBy('code_client')->get() as $client) {
            $createur = User::query()->findOrFail($client->cree_par_user_id);

            AuditLog::updateOrCreate(
                [
                    'action' => 'CLIENT_DEMO_CREATED',
                    'table_concernee' => 'clients',
                    'enregistrement_id' => $client->id,
                ],
                [
                    'user_id' => $createur->id,
                    'anciennes_valeurs' => null,
                    'nouvelles_valeurs' => [
                        'code_client' => $client->code_client,
                        'agence_id' => $client->agence_id,
                    ],
                    'description' => 'Client de demonstration cree par le seeder.',
                ]
            );
        }
    }
}
