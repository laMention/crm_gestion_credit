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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('agence_id')
                ->nullable()
                ->after('user_id')
                ->constrained('agences')
                ->nullOnDelete();
            $table->string('role_utilisateur', 30)->nullable()->after('agence_id');
            $table->string('module', 50)->nullable()->after('action');
            $table->string('type_ressource', 100)->nullable()->after('module');
            $table->string('resultat', 20)->default('succes')->after('type_ressource');
            $table->index(['agence_id', 'created_at']);
            $table->index(['module', 'action', 'created_at']);
            $table->index(['type_ressource', 'enregistrement_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['agence_id', 'created_at']);
            $table->dropIndex(['module', 'action', 'created_at']);
            $table->dropIndex(['type_ressource', 'enregistrement_id']);
            $table->dropForeign(['agence_id']);
            $table->dropColumn([
                'agence_id',
                'role_utilisateur',
                'module',
                'type_ressource',
                'resultat',
            ]);
        });
    }
};
