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
        Schema::create('echeance_credit_remboursements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')
                ->constrained('transactions')
                ->restrictOnDelete();
            $table->foreignId('echeance_credit_id')
                ->constrained('echeance_credits')
                ->restrictOnDelete();
            $table->decimal('montant', 15, 2);
            $table->decimal('capital', 15, 2);
            $table->decimal('interet', 15, 2);
            $table->timestamps();

            $table->unique(
                ['transaction_id', 'echeance_credit_id'],
                'unique_remboursement_echeance'
            );
            $table->index('echeance_credit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeance_credit_remboursements');
    }
};
