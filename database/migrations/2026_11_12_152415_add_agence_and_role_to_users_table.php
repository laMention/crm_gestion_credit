<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('agence_id')
                ->nullable()
                ->after('id')
                ->constrained('agences')
                ->nullOnDelete();

            $table->enum('role', [
                'administrateur',
                'direction',
                'chef_agence',
                'conseiller_client',
                'caissier'
            ])->default('conseiller_client')
                ->after('email');

            $table->boolean('actif')
                ->default(true);

            $table->timestamp('derniere_connexion_le')
                ->nullable();

            $table->index(['agence_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
             $table->dropIndex(['agence_id', 'role']);
            $table->dropConstrainedForeignId('agence_id');
            $table->dropColumn([
                'role',
                'actif',
                'derniere_connexion_le'
            ]);
        });
    }
};
