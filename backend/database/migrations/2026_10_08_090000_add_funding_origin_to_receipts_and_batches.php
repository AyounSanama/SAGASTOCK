<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 7 — Origine des entrées en stock : couple ONG/Bailleur (projet de la
 * FOSA) ou « Autre » (tiers). L'origine est portée par l'entrée et par le lot,
 * pour suivre le stock par couple : un même numéro de lot reçu de deux
 * bailleurs donne deux lots distincts.
 *
 * Non destructive : aucune donnée n'est modifiée. Le stock existant reste
 * « origine non renseignée » jusqu'à la commande pharmacare:stock-origins
 * (liste d'abord, application seulement sur demande).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->string('origin_type', 20)->nullable();
            $table->foreignUuid('origin_project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('origin_label', 190)->nullable();
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->string('origin_type', 20)->nullable();
            $table->foreignUuid('origin_project_id')->nullable()->constrained('projects')->nullOnDelete();
            // Clé de l'origine dans l'unicité du lot ('' : origine non renseignée, lots existants).
            $table->string('origin_key', 120)->default('');
        });

        Schema::table('batches', function (Blueprint $table) {
            $table->dropUnique(['organization_id', 'product_id', 'batch_number']);
            $table->unique(['organization_id', 'product_id', 'batch_number', 'origin_key'], 'batches_number_origin_unique');
        });
    }

    public function down(): void
    {
        Schema::table('batches', function (Blueprint $table) {
            $table->dropUnique('batches_number_origin_unique');
            $table->unique(['organization_id', 'product_id', 'batch_number']);
            $table->dropConstrainedForeignId('origin_project_id');
            $table->dropColumn(['origin_type', 'origin_key']);
        });
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('origin_project_id');
            $table->dropColumn(['origin_type', 'origin_label']);
        });
    }
};
