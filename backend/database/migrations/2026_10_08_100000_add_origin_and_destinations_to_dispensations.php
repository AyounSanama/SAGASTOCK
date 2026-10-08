<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 7 — Dispensation :
 * - couple ONG/Bailleur (origine du stock délivré) ;
 * - destinations « Périmés/détériorés » et « Retour pharmacie ONG », qui ne
 *   concernent pas un patient : le patient devient facultatif en base (il
 *   reste obligatoire pour la destination « Patient », contrôlé par l'application).
 *
 * Non destructive : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensations', function (Blueprint $table) {
            $table->string('origin_type', 20)->nullable();
            $table->foreignUuid('origin_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->uuid('patient_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('dispensations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_project_id');
            $table->dropColumn('origin_type');
        });
    }
};
