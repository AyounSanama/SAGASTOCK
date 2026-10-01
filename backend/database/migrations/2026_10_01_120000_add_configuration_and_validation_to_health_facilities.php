<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM-162 (lot c1) — Fiche FOSA complète.
 *
 * - Statut de validation : en attente → validée par la Coordination, ou
 *   refusée (motif) ; suspendue. Les FOSA existantes passent « validées ».
 * - Catégorie et niveau de soins (référentiel), populations et pathologies
 *   (limitées à la configuration du projet).
 * - Paramètres d'approvisionnement Pharmacie du projet → FOSA (DEC-08) et
 *   trois dates, avec historique.
 * - Site principal par FOSA, invisible pour l'utilisateur (DEC-05).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('health_facilities', function (Blueprint $table): void {
            $table->string('validation_status', 20)->default('validated')->index()->after('is_active');
            $table->foreignId('declared_by')->nullable()->after('validation_status')->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable()->after('declared_by');
            $table->foreignId('validated_by')->nullable()->after('validated_at')->constrained('users')->nullOnDelete();
            $table->text('refusal_reason')->nullable()->after('validated_by');
            $table->timestamp('suspended_at')->nullable()->after('refusal_reason');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
            $table->foreignUuid('facility_category_id')->nullable()->after('care_level')->constrained('catalog_references')->nullOnDelete();
            $table->foreignUuid('care_level_id')->nullable()->after('facility_category_id')->constrained('catalog_references')->nullOnDelete();
            $table->unsignedTinyInteger('order_period_months')->nullable()->after('care_level_id');
            $table->unsignedTinyInteger('delivery_lead_time_months')->nullable()->after('order_period_months');
            $table->decimal('safety_stock_months', 4, 2)->nullable()->after('delivery_lead_time_months');
            $table->date('inventory_date')->nullable()->after('safety_stock_months');
            $table->date('order_submission_date')->nullable()->after('inventory_date');
            $table->date('order_receipt_date')->nullable()->after('order_submission_date');
        });

        foreach (['target_population' => 'health_facility_target_populations', 'pathology' => 'health_facility_pathologies'] as $type => $tableName) {
            Schema::create($tableName, function (Blueprint $table) use ($type): void {
                $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid($type.'_id')->constrained('catalog_references')->cascadeOnDelete();
                $table->timestamps();
                $table->primary(['health_facility_id', $type.'_id']);
            });
        }

        Schema::create('health_facility_supply_settings_history', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('order_period_months')->nullable();
            $table->unsignedTinyInteger('delivery_lead_time_months')->nullable();
            $table->decimal('safety_stock_months', 4, 2)->nullable();
            $table->date('inventory_date')->nullable();
            $table->date('order_submission_date')->nullable();
            $table->date('order_receipt_date')->nullable();
            // creation (préremplie depuis le projet) | update | project_apply
            $table->string('source', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('effective_at');
            $table->timestamps();
        });

        Schema::table('sites', function (Blueprint $table): void {
            $table->boolean('is_primary')->default(false)->after('is_active');
        });

        // FOSA existantes : validées (seules les nouvelles passent par « en attente »).
        DB::table('health_facilities')->update(['validation_status' => 'validated', 'validated_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropColumn('is_primary'));
        Schema::dropIfExists('health_facility_supply_settings_history');
        Schema::dropIfExists('health_facility_pathologies');
        Schema::dropIfExists('health_facility_target_populations');
        Schema::table('health_facilities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('declared_by');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropConstrainedForeignId('facility_category_id');
            $table->dropConstrainedForeignId('care_level_id');
            $table->dropColumn([
                'validation_status', 'validated_at', 'refusal_reason', 'suspended_at', 'suspension_reason',
                'order_period_months', 'delivery_lead_time_months', 'safety_stock_months',
                'inventory_date', 'order_submission_date', 'order_receipt_date',
            ]);
        });
    }
};
