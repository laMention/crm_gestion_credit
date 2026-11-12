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
        Schema::create('echeance_credits', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('credit_id')
                ->constrained('credits')
                ->restrictOnDelete();

            $table->unsignedSmallInteger('numero_echeance');
            $table->date('date_echeance');
            $table->decimal('capital', 15, 2);
            $table->decimal('interet', 15, 2);
            $table->decimal('montant_echeance', 15, 2);
            $table->decimal('montant_paye', 15, 2)->default(0);
            $table->date('date_paiement')->nullable();

            $table->enum('statut_echeance_credit', [
                'a_payer',
                'partiellement_paye',
                'paye',
                'en_retard',
                'annule'
            ])->default('a_payer');
            $table->unique(
                ['credit_id', 'numero_echeance'],
                'unique_credit_echeance'
            ); // Une seule échéance par numéro pour un crédit
            $table->timestamps();
            $table->softDeletes();

            $table->index(['date_echeance', 'statut_echeance_credit']);
            $table->index(['credit_id', 'statut_echeance_credit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeance_credits');
    }
};
