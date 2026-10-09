<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 7 (décision du 09/10) : entête « Patient » de la sortie, 5 champs
 * obligatoires — nom et prénom (dossier patient), âge, sexe, n° d'ordonnance,
 * prescripteur. Âge et sexe sont figés à la date de la délivrance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensations', function (Blueprint $table): void {
            $table->string('prescription_number', 80)->nullable();
            $table->string('prescriber_name', 190)->nullable();
            $table->unsignedSmallInteger('patient_age_years')->nullable();
            $table->string('patient_sex', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('dispensations', function (Blueprint $table): void {
            $table->dropColumn(['prescription_number', 'prescriber_name', 'patient_age_years', 'patient_sex']);
        });
    }
};
