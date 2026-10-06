<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 2 — Assistant « Créer un projet / programme », étape 4.
 *
 * - Stock de sécurité du projet en mois décimaux (0,25 à 2), comme la FOSA
 *   qu'il préremplit : conversion sans perte des valeurs entières existantes.
 * - Trois dates du projet (inventaire, soumission et réception de commande),
 *   proposées par défaut aux FOSA, avec leur historique.
 */
return new class extends Migration
{
    private const DATES = ['inventory_date', 'order_submission_date', 'order_receipt_date'];

    public function up(): void
    {
        foreach (['projects', 'project_supply_settings_history'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->decimal('safety_stock_months', 4, 2)->nullable()->change();
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->date('inventory_date')->nullable()->after('safety_stock_months');
                $table->date('order_submission_date')->nullable()->after('inventory_date');
                $table->date('order_receipt_date')->nullable()->after('order_submission_date');
            });
        }
    }

    public function down(): void
    {
        foreach (['projects', 'project_supply_settings_history'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(self::DATES);
            });
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('safety_stock_months')->nullable()->change();
            });
        }
    }
};
