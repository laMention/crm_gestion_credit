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
        Schema::create('clients', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid()->unique();
            $table->foreignId('agence_id')
                ->constrained('agences')
                ->restrictOnDelete();
            
            $table->string('code_client', 30)->unique();
            $table->string('nom_client');
            $table->string('prenom_client');
            $table->string('adresse_client');
            $table->string('telephone_client');
            $table->string('ville_client');
            $table->string('email_client')->unique();
            $table->date('date_naissance')->nullable();
            $table->string('lieu_naissance')->nullable();
            $table->string('sexe', 10)->nullable();
            $table->string('type_piece_identite', 50)->nullable();
            $table->string('numero_piece_identite', 100)->nullable();
            $table->date('date_expiration_piece')->nullable();

            $table->string('profession')->nullable();
            $table->decimal('revenu_mensuel', 15, 2)->nullable();

            $table->string('statut', 20)->default('actif');
            $table->index(['agence_id', 'statut']);

            $table->foreignId('cree_par_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->rememberToken();
            $table->timestamps();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
