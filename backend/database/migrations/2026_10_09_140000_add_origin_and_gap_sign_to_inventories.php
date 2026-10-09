<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 7 :
 * - un inventaire porte sur un couple ONG/Bailleur (ou « Autre ») et ne
 *   compte que les lots de ce couple ;
 * - signe de l'écart aligné sur le cahier : écart = théorique − inventorié
 *   (positif = manquant). Les écarts déjà enregistrés sont convertis ; les
 *   mouvements de stock passés ne changent pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->string('origin_type', 20)->nullable();
            $table->uuid('origin_project_id')->nullable()->index();
        });
        $this->flipGapSign();
    }

    public function down(): void
    {
        $this->flipGapSign();
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn(['origin_type', 'origin_project_id']);
        });
    }

    private function flipGapSign(): void
    {
        DB::table('inventory_lines')->whereNotNull('variance_quantity')->update([
            'variance_quantity' => DB::raw('-variance_quantity'),
            'variance_value' => DB::raw('-COALESCE(variance_value, 0)'),
        ]);
        DB::table('inventories')->whereNotNull('variance_value')->update(['variance_value' => DB::raw('-variance_value')]);
    }
};
