<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Configuration Mission — populations cibles proposées pour chaque niveau de
 * soins (référentiel global) : Adultes, Femmes enceintes, Enfants < 5 ans.
 * Les organisations peuvent en ajouter d'autres. Insertion idempotente.
 */
return new class extends Migration
{
    private const POPULATIONS = [
        'POP-ADULT' => 'Adultes',
        'POP-PW' => 'Femmes enceintes',
        'POP-U5' => 'Enfants < 5 ans',
    ];

    public function up(): void
    {
        foreach (self::POPULATIONS as $code => $name) {
            $exists = DB::table('catalog_references')->whereNull('organization_id')
                ->where('reference_type', 'target_population')->where('code', $code)->exists();
            if (! $exists) {
                DB::table('catalog_references')->insert([
                    'id' => (string) Str::uuid(), 'organization_id' => null, 'reference_type' => 'target_population',
                    'parent_id' => null, 'depth' => 1, 'code' => $code, 'name' => $name,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Données de référence : conservées, elles peuvent être liées à des configurations.
    }
};
