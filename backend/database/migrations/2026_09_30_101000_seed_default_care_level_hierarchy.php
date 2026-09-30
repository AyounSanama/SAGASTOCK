<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * AM-111 — Hiérarchie initiale issue du cahier des charges (référentiel global).
 *
 * Soins de santé primaire / secondaire → Programmes de prise en charge →
 * PEC VIH, Paludisme, Malnutrition, Tuberculose. Les organisations peuvent y
 * rattacher leurs propres catégories et programmes. Insertion idempotente.
 */
return new class extends Migration
{
    private const PROGRAMS = [
        'VIH' => 'Programme PEC VIH',
        'PALU' => 'Programme PEC Paludisme',
        'MALNUT' => 'Programme PEC Malnutrition',
        'TB' => 'Programme PEC Tuberculose',
    ];

    public function up(): void
    {
        foreach (['SSP' => 'Soins de santé primaire', 'SSS' => 'Soins de santé secondaire'] as $code => $name) {
            $level = $this->node($code, $name, null, 1);
            $category = $this->node("$code-PEC", 'Programmes de prise en charge', $level, 2);
            foreach (self::PROGRAMS as $programCode => $programName) {
                $this->node("$code-PEC-$programCode", $programName, $category, 3);
            }
        }
    }

    public function down(): void
    {
        // Données de référence : conservées, elles peuvent être liées à des listes publiées.
    }

    private function node(string $code, string $name, ?string $parentId, int $depth): string
    {
        $existing = DB::table('catalog_references')->whereNull('organization_id')
            ->where('reference_type', 'care_level')->where('code', $code)->value('id');
        if ($existing) {
            return $existing;
        }
        $id = (string) Str::uuid();
        DB::table('catalog_references')->insert([
            'id' => $id, 'organization_id' => null, 'reference_type' => 'care_level',
            'parent_id' => $parentId, 'depth' => $depth, 'code' => $code, 'name' => $name,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }
};
