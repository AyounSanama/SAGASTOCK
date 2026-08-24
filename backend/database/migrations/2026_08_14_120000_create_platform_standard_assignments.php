<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_standard_assignments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('platform_standard_id');
            $table->uuid('platform_standard_version_id');
            $table->string('status', 20)->default('published')->index();
            $table->uuid('assigned_by')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('platform_standard_id')->references('id')->on('platform_standards')->cascadeOnDelete();
            $table->foreign('platform_standard_version_id')->references('id')->on('platform_standard_versions')->cascadeOnDelete();
            $table->foreign('assigned_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['organization_id', 'platform_standard_id', 'status'], 'platform_assignment_current_idx');
        });

        Schema::create('organization_effective_configurations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_id');
            $table->uuid('platform_standard_id');
            $table->uuid('platform_standard_assignment_id');
            $table->unsignedInteger('configuration_version');
            $table->unsignedInteger('standard_version_number');
            $table->json('configuration');
            $table->string('checksum', 64);
            $table->string('status', 20)->default('active')->index();
            $table->timestamp('effective_at');
            $table->uuid('applied_by')->nullable();
            $table->timestamps();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('platform_standard_id')->references('id')->on('platform_standards')->cascadeOnDelete();
            $table->foreign('platform_standard_assignment_id')->references('id')->on('platform_standard_assignments')->cascadeOnDelete();
            $table->foreign('applied_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['organization_id', 'platform_standard_id', 'configuration_version'], 'organization_effective_config_version_unique');
            $table->index(['organization_id', 'status'], 'organization_effective_current_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_effective_configurations');
        Schema::dropIfExists('platform_standard_assignments');
    }
};
