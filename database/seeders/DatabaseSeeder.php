<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AgenceSeeder::class,
            UserSeeder::class,
            ClientSeeder::class,
            CompteSeeder::class,
            CreditSeeder::class,
            EcheanceCreditSeeder::class,
            TransactionSeeder::class,
            ReportingSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
