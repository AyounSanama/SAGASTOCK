<?php

namespace App\Services;

use App\Enums\ConfigurationFlowType;
use App\Enums\ConfigurationStepState;
use App\Models\SetupProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConfigurationWorkflowService
{
    public const REQUEST_KEY = '_flow';
    private const SESSION_KEY = 'configuration_workflow_id';

    public function start(Request $request, ConfigurationFlowType $type): SetupProgress
    {
        $states = $this->initialStates($type);
        $progress = SetupProgress::create([
            'workflow_id' => (string) Str::uuid(),
            'flow_type' => $type->value,
            'start_step' => $type->startStep(),
            'current_step' => $type->startStep(),
            'completed_steps' => $type->initiallyCompletedSteps(),
            'step_states' => $states,
            'drafts' => [],
            'version' => 1,
            'scope_type' => 'platform',
            'workflow_status' => 'active',
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasSession()) {
            $request->session()->put(self::SESSION_KEY, $progress->workflow_id);
        }

        return $progress;
    }

    public function resolve(
        Request $request,
        ConfigurationFlowType $fallback = ConfigurationFlowType::InitialConfiguration,
    ): SetupProgress {
        $workflowId = $request->input(self::REQUEST_KEY)
            ?? $request->query(self::REQUEST_KEY)
            ?? ($request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null);

        $progress = $workflowId
            ? SetupProgress::query()
                ->where('workflow_id', $workflowId)
                ->where('created_by', $request->user()->id)
                ->first()
            : null;

        if (! $workflowId) {
            $progress = SetupProgress::query()
                ->where('flow_type', ConfigurationFlowType::InitialConfiguration->value)
                ->where(fn ($query) => $query
                    ->where('created_by', $request->user()->id)
                    ->orWhereNull('created_by'))
                ->latest('id')
                ->first();
            if ($progress && ! $progress->created_by) {
                $progress->update(['created_by' => $request->user()->id]);
            }
        }

        if (! $progress) {
            $progress = $this->start($request, $fallback);
        } else {
            if (! $progress->step_states) {
                $progress->update([
                    'step_states' => $this->statesFromCompleted(
                        $progress->completed_steps ?? [],
                        $progress->current_step,
                    ),
                    'drafts' => $progress->drafts ?? [],
                ]);
            }
            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $progress->workflow_id);
            }
        }

        return $progress;
    }

    public function complete(SetupProgress $progress, int $step): void
    {
        $completed = collect($progress->completed_steps ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value <= $step)
            ->push($step)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $progress->update([
            'completed_steps' => $completed,
            'step_states' => $this->transitionToValid($progress, $step),
            'drafts' => collect($progress->drafts ?? [])
                ->except((string) $step)
                ->all(),
            'current_step' => min(12, $step + 1),
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function invalidateFrom(SetupProgress $progress, int $step): void
    {
        $completed = collect($progress->completed_steps ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter(fn (int $value) => $value < $step)
            ->values()
            ->all();

        $progress->update([
            'completed_steps' => $completed,
            'step_states' => $this->invalidatedStates($progress, $step),
            'current_step' => max($progress->start_step, $step),
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function parameters(SetupProgress $progress, array $parameters = []): array
    {
        return [...$parameters, self::REQUEST_KEY => $progress->workflow_id];
    }

    public function requestParameters(
        Request $request,
        SetupProgress $progress,
        array $parameters = [],
    ): array {
        if ($request->filled(self::REQUEST_KEY)
            || $progress->flow_type !== ConfigurationFlowType::InitialConfiguration) {
            $parameters[self::REQUEST_KEY] = $progress->workflow_id;
        }

        return $parameters;
    }

    public function remember(SetupProgress $progress, string $key, mixed $value): void
    {
        $progress->update([
            'context' => [...($progress->context ?? []), $key => $value],
        ]);
    }

    public function saveDraft(SetupProgress $progress, int $step, array $payload): void
    {
        $safe = collect($payload)
            ->except([
                '_token',
                '_method',
                '_flow',
                'password',
                'password_confirmation',
                'confirmation',
            ])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->all();
        $drafts = $progress->drafts ?? [];
        $drafts[(string) $step] = [
            'data' => $safe,
            'saved_at' => now()->toIso8601String(),
        ];
        $states = $progress->step_states ?? [];
        if (($states[(string) $step] ?? null) !== ConfigurationStepState::Valid->value) {
            $states[(string) $step] = ConfigurationStepState::InProgress->value;
        }
        $progress->update(['drafts' => $drafts, 'step_states' => $states]);
    }

    public function restoreDraft(Request $request, SetupProgress $progress, int $step): void
    {
        if (! $request->hasSession()) return;
        if ($request->session()->getOldInput()) return;
        $draft = data_get($progress->drafts, "{$step}.data");
        if (is_array($draft) && $draft !== []) {
            $request->session()->flashInput($draft);
        }
    }

    public function states(SetupProgress $progress): array
    {
        return $progress->step_states
            ?? $this->statesFromCompleted(
                $progress->completed_steps ?? [],
                $progress->current_step,
            );
    }

    private function initialStates(ConfigurationFlowType $type): array
    {
        $states = [];
        for ($step = 1; $step <= 12; $step++) {
            $states[(string) $step] = $step < $type->startStep()
                ? ConfigurationStepState::Valid->value
                : ($step === $type->startStep()
                    ? ConfigurationStepState::InProgress->value
                    : ConfigurationStepState::NotStarted->value);
        }

        return $states;
    }

    private function statesFromCompleted(array $completed, int $current): array
    {
        $states = [];
        $completed = array_map('intval', $completed);
        for ($step = 1; $step <= 12; $step++) {
            $states[(string) $step] = in_array($step, $completed, true)
                ? ConfigurationStepState::Valid->value
                : ($step === $current
                    ? ConfigurationStepState::InProgress->value
                    : ConfigurationStepState::NotStarted->value);
        }

        return $states;
    }

    private function transitionToValid(SetupProgress $progress, int $step): array
    {
        $states = $this->states($progress);
        for ($number = $step + 1; $number <= 12; $number++) {
            $states[(string) $number] = ConfigurationStepState::NotStarted->value;
        }
        $states[(string) $step] = ConfigurationStepState::Valid->value;
        if ($step < 12) {
            $states[(string) ($step + 1)] = ConfigurationStepState::InProgress->value;
        }

        return $states;
    }

    private function invalidatedStates(SetupProgress $progress, int $step): array
    {
        $states = $this->states($progress);
        for ($number = $step; $number <= 12; $number++) {
            $states[(string) $number] = $number === $step
                ? ConfigurationStepState::NeedsCorrection->value
                : ConfigurationStepState::NotStarted->value;
        }

        return $states;
    }
}
