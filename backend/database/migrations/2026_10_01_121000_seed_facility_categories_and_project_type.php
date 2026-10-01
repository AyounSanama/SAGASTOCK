<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * AM-162 — Catégories de FOSA du référentiel commun (DEC-11) et type de
 * projet (décision E1) : projet bailleur ou programme national.
 */
return new class extends Migration
{
    /** Code => [libellé, niveau de soins habituel (indication)]. */
    private const CATEGORIES = [
        'CAT-HR' => 'Hôpital régional',
        'CAT-HD' => 'Hôpital de district',
        'CAT-CMA' => 'Centre médical d’arrondissement (CMA)',
        'CAT-CSI' => 'Centre de santé intégré (CSI)',
        'CAT-CSA' => 'Centre de santé ambulatoire',
    ];

    public function up(): void
    {
        foreach (self::CATEGORIES as $code => $name) {
            $exists = DB::table('catalog_references')->whereNull('organization_id')
                ->where('reference_type', 'facility_category')->where('code', $code)->exists();
            if (! $exists) {
                DB::table('catalog_references')->insert([
                    'id' => (string) Str::uuid(), 'organization_id' => null, 'reference_type' => 'facility_category',
                    'parent_id' => null, 'depth' => 1, 'code' => $code, 'name' => $name,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        Schema::table('projects', function (Blueprint $table): void {
            // donor_project : un bailleur au plus ; national_program : bailleur facultatif
            // (« Ministère de la Santé » par défaut, modifiable).
            $table->string('type', 20)->default('donor_project')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('type'));
    }
};
