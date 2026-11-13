<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', [
                'administrateur',
                'direction',
                'chef_agence',
                'conseiller_client',
                'conseiller',
                'caissier',
            ])->default('conseiller_client')->change();
        });

        DB::table('users')
            ->where('role', 'conseiller_client')
            ->update(['role' => 'conseiller']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', [
                'administrateur',
                'direction',
                'chef_agence',
                'conseiller',
                'caissier',
            ])->default('conseiller')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', [
                'administrateur',
                'direction',
                'chef_agence',
                'conseiller_client',
                'conseiller',
                'caissier',
            ])->default('conseiller')->change();
        });

        DB::table('users')
            ->where('role', 'conseiller')
            ->update(['role' => 'conseiller_client']);

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', [
                'administrateur',
                'direction',
                'chef_agence',
                'conseiller_client',
                'caissier',
            ])->default('conseiller_client')->change();
        });
    }
};
