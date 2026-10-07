<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Langues choisies par chaque Coordination (et non plus par l'Admin Sago).
 * Valeurs de départ : celles de l'organisation, pour ne rien perdre.
 * Non destructive : les colonnes de l'organisation sont conservées.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'default_language')) {
                $table->string('default_language', 10)->nullable();
            }
            if (! Schema::hasColumn('missions', 'additional_languages')) {
                $table->json('additional_languages')->nullable();
            }
        });

        DB::table('missions')->whereNull('default_language')->orderBy('id')->get(['id', 'organization_id'])
            ->each(function ($mission): void {
                $organization = DB::table('organizations')->where('id', $mission->organization_id)->first(['default_language', 'additional_languages']);
                DB::table('missions')->where('id', $mission->id)->update([
                    'default_language' => $organization?->default_language ?: 'fr',
                    'additional_languages' => $organization?->additional_languages,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['default_language', 'additional_languages']);
        });
    }
};
