<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Niveau 6 — Listes Standard.
 *
 * - Niveau de soins « Programme Laboratoire » (référentiel global, cahier des
 *   charges) : ses examens de laboratoire sont proposés comme activités.
 * - Décochage d'articles par FOSA : produits de la Liste Standard du projet
 *   retirés pour une formation sanitaire, par la Coordination.
 *
 * Non destructive et idempotente : aucune donnée existante n'est modifiée.
 */
return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('catalog_references')->whereNull('organization_id')
            ->where('reference_type', 'care_level')->where('code', 'LAB')->exists();
        if (! $exists) {
            DB::table('catalog_references')->insert([
                'id' => (string) Str::uuid(), 'organization_id' => null, 'reference_type' => 'care_level',
                'parent_id' => null, 'depth' => 1, 'code' => 'LAB', 'name' => 'Programme Laboratoire',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('health_facility_product_exclusions')) {
            Schema::create('health_facility_product_exclusions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
                $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
                $table->string('reason', 500)->nullable();
                $table->foreignId('excluded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['health_facility_id', 'product_id'], 'hf_product_exclusion_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('health_facility_product_exclusions');
        // « Programme Laboratoire » est conservé : il peut être lié à des projets.
    }
};
