<?php

namespace App\Models;

use App\Enums\ConfigurationFlowType;
use Illuminate\Database\Eloquent\Model;

class SetupProgress extends Model
{
    protected $table = 'setup_progress';
    protected $fillable = [
        'workflow_id',
        'flow_type',
        'start_step',
        'current_step',
        'context',
        'completed_steps',
        'step_states',
        'drafts',
        'version',
        'scope_type',
        'scope_id',
        'workflow_status',
        'completed_at',
        'completed_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'step_states' => 'array',
            'drafts' => 'array',
            'completed_at' => 'datetime',
            'flow_type' => ConfigurationFlowType::class,
            'context' => 'array',
        ];
    }

    public static function current(): self
    {
        return static::query()
            ->where('flow_type', ConfigurationFlowType::InitialConfiguration->value)
            ->latest('id')
            ->firstOrCreate([], [
                'workflow_id' => (string) \Illuminate\Support\Str::uuid(),
                'flow_type' => ConfigurationFlowType::InitialConfiguration,
                'start_step' => 1,
                'current_step' => 1,
                'completed_steps' => [],
            ]);
    }
}
