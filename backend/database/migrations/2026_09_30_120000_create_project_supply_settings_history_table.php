<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

/**
 * AM-114 — Historique append-only des paramètres d'approvisionnement.
 *
 * Permet de savoir quelle périodicité, quel délai et quel stock de sécurité
 * s'appliquaient à une date donnée (calcul des commandes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_supply_settings_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('order_period_months')->nullable();
            $table->unsignedSmallInteger('delivery_lead_time_months')->nullable();
            $table->unsignedSmallInteger('safety_stock_months')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamp('created_at')->nullable();
            $table->index(['project_id', 'effective_at']);
        });

        // Point de départ : valeurs actuelles des projets existants.
        DB::table('projects')->whereNull('deleted_at')->orderBy('id')->each(function ($project): void {
            if ($project->order_period_months || $project->delivery_lead_time_months || $project->safety_stock_months) {
                DB::table('project_supply_settings_history')->insert([
                    'id' => (string) Str::uuid(), 'project_id' => $project->id,
                    'order_period_months' => $project->order_period_months,
                    'delivery_lead_time_months' => $project->delivery_lead_time_months,
                    'safety_stock_months' => $project->safety_stock_months,
                    'changed_by' => null, 'effective_at' => $project->updated_at ?? now(), 'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_supply_settings_history');
    }
};
