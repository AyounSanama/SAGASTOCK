<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table): void {
            $table->foreignUuid('health_facility_id')->nullable()->after('organization_id')->constrained()->nullOnDelete();
            $table->foreignUuid('site_id')->nullable()->after('health_facility_id')->constrained()->nullOnDelete();
            $table->index(['organization_id', 'health_facility_id', 'site_id'], 'patients_fosa_scope_index');
        });

        Schema::table('prescriptions', function (Blueprint $table): void {
            $table->string('attachment_original_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime_type', 120)->nullable()->after('attachment_original_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime_type');
            $table->timestamp('attachment_captured_at')->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', fn (Blueprint $table) => $table->dropColumn([
            'attachment_original_name', 'attachment_mime_type', 'attachment_size', 'attachment_captured_at',
        ]));
        Schema::table('patients', function (Blueprint $table): void {
            $table->dropIndex('patients_fosa_scope_index');
            $table->dropConstrainedForeignId('site_id');
            $table->dropConstrainedForeignId('health_facility_id');
        });
    }
};
