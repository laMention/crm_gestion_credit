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
        Schema::create('transactions', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid()->unique();            
            $table->string('reference_transaction',30)->unique();
            $table->foreignId('agence_id') //Agence responsable de l'operation
                ->constrained('agences')
                ->restrictOnDelete();

            $table->foreignId('client_id') //Client qui effectue la transaction
                ->constrained('clients')
                ->restrictOnDelete();

            $table->foreignId('user_id') // Utilisateur ayant effectué l'opération
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->enum('type_transaction', [
                'depot',
                'retrait',
                'virement',
                'paiement_facture',
                'deboursement',
                'remboursement'
            ]);
            $table->decimal('montant', 15, 2);
            $table->string('devise', 3)->default('XOF');
            $table->dateTime('date_transaction')->nullable();          

            $table->foreignId('compte_source_id') //Compte à partir duquel la transaction est effectuée
                ->nullable()
                ->constrained('comptes')
                ->nullOnDelete();

            $table->foreignId('compte_destinataire_id') //Compte vers lequel la transaction est effectuée
                ->nullable()
                ->constrained('comptes')
                ->nullOnDelete();

            $table->enum('statut_transaction', [
                'en_attente',
                'terminee',
                'annulee'
            ])->default('en_attente');

            $table->foreignId('valide_par')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('date_validation')->nullable();

            $table->string('cle_idempotence', 100) //Clé unique pour éviter les doublons de transaction
                ->nullable()
                ->unique();
            $table->text('motif')->nullable();
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['agence_id', 'type_transaction']);
            $table->index(['agence_id', 'date_transaction']);
            $table->index(['client_id', 'date_transaction']);
            $table->index(['statut_transaction', 'date_transaction']);
            $table->index('reference_transaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
