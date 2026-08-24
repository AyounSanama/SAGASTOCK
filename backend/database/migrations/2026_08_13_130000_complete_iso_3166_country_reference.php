<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('countries', 'iso3')) {
            Schema::table('countries', fn (Blueprint $table) => $table->string('iso3', 3)->nullable()->unique()->after('iso2'));
        }

        $names = (require base_path('vendor/symfony/intl/Resources/data/regions/fr.php'))['Names'];
        $meta = require base_path('vendor/symfony/intl/Resources/data/regions/meta.php');
        $alpha3 = $meta['Alpha2ToAlpha3'];
        $now = now();

        foreach ($alpha3 as $iso2 => $iso3) {
            DB::table('countries')->updateOrInsert(
                ['iso2' => $iso2],
                ['id' => DB::table('countries')->where('iso2', $iso2)->value('id') ?: (string) Str::uuid(),
                 'iso3' => $iso3, 'name' => $names[$iso2] ?? $iso2,
                 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('countries', 'iso3')) {
            Schema::table('countries', fn (Blueprint $table) => $table->dropColumn('iso3'));
        }
    }
};
