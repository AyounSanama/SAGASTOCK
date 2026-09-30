<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * AM-112 — Configuration médicale du projet.
 *
 * Projet → niveaux de soins retenus, populations cibles, et pathologies
 * associées à chaque population. Tables relationnelles (plus de JSON) pour
 * garantir l'intégrité référentielle sous PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_care_levels', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('care_level_id')->constrained('catalog_references')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'care_level_id']);
        });

        Schema::create('project_target_populations', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('target_population_id')->constrained('catalog_references')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'target_population_id']);
        });

        Schema::create('project_pathology_populations', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('pathology_id')->constrained('catalog_references')->restrictOnDelete();
            $table->foreignUuid('target_population_id')->constrained('catalog_references')->restrictOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'pathology_id', 'target_population_id'], 'project_pathology_population_primary');
            $table->index(['project_id', 'target_population_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pathology_populations');
        Schema::dropIfExists('project_target_populations');
        Schema::dropIfExists('project_care_levels');
    }
};
