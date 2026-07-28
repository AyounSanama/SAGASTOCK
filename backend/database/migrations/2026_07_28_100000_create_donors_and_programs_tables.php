<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('programs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('donor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('project_donors', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('donor_id')->constrained()->cascadeOnDelete();
            $table->decimal('funding_amount', 18, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('agreement_reference', 120)->nullable();
            $table->timestamps();
            $table->primary(['project_id', 'donor_id']);
        });

        Schema::create('program_project', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['project_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_project');
        Schema::dropIfExists('project_donors');
        Schema::dropIfExists('programs');
        Schema::dropIfExists('donors');
    }
};
