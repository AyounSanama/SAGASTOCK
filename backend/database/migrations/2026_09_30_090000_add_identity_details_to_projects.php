<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AM-110 — Fiche projet enrichie (cahier des charges « Créer un projet »).
 *
 * `status` devient la source du cycle de vie ; `is_active` est conservé et
 * dérivé (actif ⇔ status = active) pour ne casser aucun client existant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('implementing_partner', 190)->nullable()->after('name');
            $table->string('donor_reference_code', 120)->nullable()->after('implementing_partner');
            $table->string('moh_program_code', 120)->nullable()->after('donor_reference_code');
            $table->string('responsible_name', 160)->nullable()->after('moh_program_code');
            $table->string('responsible_contact', 190)->nullable()->after('responsible_name');
            $table->string('status', 20)->default('active')->after('safety_stock_months')->index();
        });

        DB::table('projects')->where('is_active', false)->update(['status' => 'suspended']);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn([
                'implementing_partner', 'donor_reference_code', 'moh_program_code',
                'responsible_name', 'responsible_contact', 'status',
            ]);
        });
    }
};
