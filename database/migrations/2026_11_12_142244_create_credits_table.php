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
        Schema::create('credits', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid()->unique();
            $table->foreignId('compte_id') //Compte associé au crédit demandé
                ->unique()
                ->constrained('comptes')
                ->restrictOnDelete();

            $table->foreignId('conseiller_client_id') //Conseiller client ayant traité le credit
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->foreignId('valide_par') // Utilisateur ayant validé le crédit (chef agence)
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            
            $table->decimal('montant_demande', 15, 2);
            $table->decimal('montant_accorde', 15, 2)->nullable();

            $table->decimal('taux_interet_annuel', 8, 4);
            $table->unsignedSmallInteger('duree_mois');

            $table->decimal('mensualite', 15, 2)->nullable();
            $table->decimal('capital_restant_du', 15, 2)->nullable();

            $table->decimal('montant_total_interets', 15, 2)
                ->nullable();
            $table->date('date_demande');
            $table->date('date_approbation')->nullable();
            $table->date('date_deboursement')->nullable();
            $table->date('date_debut_remboursement')->nullable();
            $table->date('date_fin_prevue')->nullable();
            $table->enum('statut', [
                'en_attente',
                'en_etude',
                'approuve',
                'rejete',
                'debourse',
                'en_remboursement',
                'solde',
                'en_retard',
                'annule'
            ])->default('en_attente');
            $table->text('motif_rejet')->nullable();
            $table->text('observations')->nullable();           

            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'date_demande']);
            $table->index('date_deboursement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credits');
    }
};
