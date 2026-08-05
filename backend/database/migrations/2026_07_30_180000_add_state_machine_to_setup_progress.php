<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setup_progress', function (Blueprint $table) {
            $table->json('step_states')->nullable()->after('completed_steps');
            $table->json('drafts')->nullable()->after('step_states');
            $table->unsignedInteger('version')->default(1)->after('drafts');
            $table->string('scope_type', 30)->default('platform')->after('version');
            $table->string('scope_id', 64)->nullable()->after('scope_type');
            $table->string('workflow_status', 20)->default('active')->after('scope_id');
        });

        DB::table('setup_progress')->orderBy('id')->each(function ($row) {
            $completed = array_map('intval', json_decode($row->completed_steps ?? '[]', true));
            $states = [];
            for ($step = 1; $step <= 12; $step++) {
                $states[(string) $step] = in_array($step, $completed, true)
                    ? 'valid'
                    : ($step === (int) ($row->current_step ?? 1) ? 'in_progress' : 'not_started');
            }
            DB::table('setup_progress')->where('id', $row->id)->update([
                'step_states' => json_encode($states),
                'drafts' => json_encode([]),
                'workflow_status' => $row->completed_at ? 'completed' : 'active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('setup_progress', function (Blueprint $table) {
            $table->dropColumn([
                'step_states',
                'drafts',
                'version',
                'scope_type',
                'scope_id',
                'workflow_status',
            ]);
        });
    }
};
