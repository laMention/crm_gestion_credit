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
        Schema::create('agences', function (Blueprint $table) {
            $table->engine('innoDB');
            $table->id();  
            $table->uuid()->unique();
            $table->string('code_agence', 10)->unique();
            $table->string('nom_agence');
            $table->string('adresse_agence');
            $table->string('ville_agence');
            $table->string('telephone_agence');
            $table->string('email_agence');
            $table->boolean('actif')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index('ville_agence');
            $table->index('actif');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agences');
    }
};
