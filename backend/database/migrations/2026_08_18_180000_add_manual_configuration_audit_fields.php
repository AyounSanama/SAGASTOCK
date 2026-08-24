<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_effective_configurations', function (Blueprint $table): void {
            $table->string('configuration_category', 32)->nullable()->after('organization_id')->index();
            $table->string('intervention_action', 32)->default('applied')->after('configuration_category')->index();
            $table->unsignedInteger('previous_configuration_version')->nullable()->after('configuration_version');
            $table->json('changes')->nullable()->after('configuration');
        });
    }

    public function down(): void
    {
        Schema::table('organization_effective_configurations', function (Blueprint $table): void {
            $table->dropIndex(['configuration_category']);
            $table->dropColumn(['configuration_category', 'intervention_action', 'previous_configuration_version', 'changes']);
        });
    }
};
