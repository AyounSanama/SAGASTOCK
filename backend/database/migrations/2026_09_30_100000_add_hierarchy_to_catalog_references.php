<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AM-111 — Niveaux de soins hiérarchiques : Niveau → Catégorie → Programme.
 *
 * La hiérarchie réutilise le référentiel existant (type `care_level`) : un
 * nœud racine est un niveau de soins, ses enfants des catégories, puis des
 * programmes cliniques (PEC VIH, Paludisme…). Les programmes de financement
 * (`programs`) restent un objet distinct (décision DEC-02).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalog_references', function (Blueprint $table) {
            $table->foreignUuid('parent_id')->nullable()->after('reference_type')
                ->constrained('catalog_references')->restrictOnDelete();
            $table->unsignedTinyInteger('depth')->default(1)->after('parent_id');
            $table->index(['reference_type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::table('catalog_references', function (Blueprint $table) {
            $table->dropIndex(['reference_type', 'parent_id']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('depth');
        });
    }
};
