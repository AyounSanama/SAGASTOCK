<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispensations', function (Blueprint $table): void {
            $table->string('prescription_attachment_path')->nullable()->after('prescription_id');
            $table->string('prescription_attachment_original_name')->nullable()->after('prescription_attachment_path');
            $table->string('prescription_attachment_mime_type', 120)->nullable()->after('prescription_attachment_original_name');
            $table->unsignedBigInteger('prescription_attachment_size')->nullable()->after('prescription_attachment_mime_type');
            $table->timestamp('prescription_attachment_captured_at')->nullable()->after('prescription_attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('dispensations', fn (Blueprint $table) => $table->dropColumn([
            'prescription_attachment_path', 'prescription_attachment_original_name',
            'prescription_attachment_mime_type', 'prescription_attachment_size',
            'prescription_attachment_captured_at',
        ]));
    }
};
