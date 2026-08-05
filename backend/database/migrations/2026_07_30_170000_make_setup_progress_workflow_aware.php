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
        Schema::table('setup_progress', function (Blueprint $table) {
            $table->uuid('workflow_id')->nullable()->unique()->after('id');
            $table->string('flow_type', 40)->default('initial-configuration')->after('workflow_id');
            $table->unsignedTinyInteger('start_step')->default(1)->after('flow_type');
            $table->unsignedTinyInteger('current_step')->default(1)->after('start_step');
            $table->json('context')->nullable()->after('current_step');
            $table->foreignId('created_by')->nullable()->after('completed_by')
                ->constrained('users')->nullOnDelete();
        });

        DB::table('setup_progress')->whereNull('workflow_id')->orderBy('id')->each(function ($row) {
            DB::table('setup_progress')->where('id', $row->id)->update([
                'workflow_id' => (string) Str::uuid(),
                'flow_type' => 'initial-configuration',
                'start_step' => 1,
                'current_step' => max(1, count(json_decode($row->completed_steps ?? '[]', true)) + 1),
                'created_by' => $row->completed_by,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('setup_progress', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['workflow_id']);
            $table->dropColumn(['workflow_id', 'flow_type', 'start_step', 'current_step', 'context']);
        });
    }
};
