<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_standard_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('platform_standard_id');
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft')->index();
            $table->json('snapshot');
            $table->text('change_notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('published_by')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['platform_standard_id', 'version_number'], 'platform_standard_version_unique');
            $table->foreign('platform_standard_id')->references('id')->on('platform_standards')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('published_by')->references('id')->on('users')->nullOnDelete();
        });

        foreach (DB::table('platform_standards')->orderBy('created_at')->get() as $standard) {
            DB::table('platform_standard_versions')->insert([
                'id' => (string) Str::uuid(), 'platform_standard_id' => $standard->id,
                'version_number' => 1, 'status' => 'draft',
                'snapshot' => json_encode([
                    'category' => $standard->category, 'code' => $standard->code, 'name' => $standard->name,
                    'description' => $standard->description,
                    'definition' => json_decode($standard->definition ?: '[]', true),
                    'is_active' => (bool) $standard->is_active,
                ]),
                'created_by' => $standard->created_by, 'created_at' => $standard->created_at, 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_standard_versions');
    }
};
