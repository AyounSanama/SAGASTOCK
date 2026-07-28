<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('health_facilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('mission_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('facility_type', 50);
            $table->string('care_level', 50)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('health_facility_project', function (Blueprint $table) {
            $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['health_facility_id', 'project_id']);
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 160);
            $table->string('department_type', 50)->default('clinical');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['health_facility_id', 'code']);
        });

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 160);
            $table->string('pharmacy_type', 50)->default('central');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['health_facility_id', 'code']);
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('health_facility_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('pharmacy_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 160);
            $table->string('site_type', 50);
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['health_facility_id', 'code']);
        });

        Schema::create('module_activations', function (Blueprint $table) {
            $table->id();
            $table->string('target_type', 30);
            $table->uuid('target_id');
            $table->string('module_code', 80);
            $table->boolean('is_enabled')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['target_type', 'target_id', 'module_code']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_activations');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('pharmacies');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('health_facility_project');
        Schema::dropIfExists('health_facilities');
    }
};
