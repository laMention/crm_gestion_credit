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
        Schema::create('comptes', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid()->unique();
            $table->foreignId('agence_id')
                ->constrained('agences')
                ->restrictOnDelete();

            $table->foreignId('client_id')
                ->constrained('clients')
                ->restrictOnDelete();
                
            $table->string('numero_compte', 30)->unique();
            $table->enum('type_compte', [
                'courant',
                'epargne',
                'credit'
            ]);
            $table->decimal('solde', 15, 2)->default(0);
            $table->string('devise', 3)->default('XOF');
            $table->date('date_ouverture');
            $table->date('date_cloture')->nullable();
            $table->enum('statut_compte', [
                'actif',
                'bloque',
                'cloture'
            ])->default('actif');
            $table->index(['client_id', 'type_compte', 'statut_compte']);
            $table->index(['agence_id', 'statut_compte']);
            $table->timestamps();

            $table->foreignId('cree_par_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comptes');
    }
};
