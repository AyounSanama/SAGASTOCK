<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('organization_type', 50)->nullable()->after('name');
            $table->string('logo_path')->nullable()->after('legal_name');
            $table->string('default_language', 10)->default('fr')->after('country_code');
            $table->string('manager_name', 160)->nullable()->after('address');
            $table->string('manager_title', 160)->nullable()->after('manager_name');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'organization_type',
                'logo_path',
                'default_language',
                'manager_name',
                'manager_title',
            ]);
        });
    }
};
