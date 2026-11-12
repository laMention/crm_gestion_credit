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
        Schema::create('reportings', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('agence_id')
                ->constrained('agences')
                ->restrictOnDelete();

            $table->date('date_reporting');
            $table->decimal('total_depots', 15, 2)->default(0);
            $table->decimal('total_retraits', 15, 2)->default(0);
            $table->decimal('total_virements', 15, 2)->default(0);
            $table->decimal('total_deboursements', 15, 2)->default(0);
            $table->decimal('total_remboursements', 15, 2)->default(0);
            $table->unsignedInteger('nombre_depots')->default(0);
            $table->unsignedInteger('nombre_retraits')->default(0);
            $table->unsignedInteger('nombre_virements')->default(0);
            $table->unsignedInteger('nombre_credits_debourses')->default(0);
            $table->unsignedInteger('nombre_remboursements')->default(0);
            $table->unsignedInteger('nombre_nouveaux_clients')->default(0);
            $table->unsignedInteger('nombre_credits_en_cours')->default(0);

            $table->decimal('encours_credits', 15, 2)->default(0);
            $table->timestamp('genere_le')->nullable();

            $table->enum('statut_generation', [
                'en_attente',
                'en_cours',
                'termine',
                'echec'
            ])->default('en_attente');

            $table->timestamps();
             $table->unique(
                ['agence_id', 'date_reporting'],
                'unique_reporting_agence_date'
            );

            $table->index('date_reporting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportings');
    }
};
