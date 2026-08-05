<?php

namespace App\Http\Controllers\Web;

use App\Enums\ConfigurationFlowType;
use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\SetupProgress;
use App\Services\AuditService;
use App\Services\ConfigurationWorkflowService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MissionConfigurationController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly UserScopeService $scopes,
        private readonly ConfigurationWorkflowService $workflows,
    ) {}

    public function show(Request $request): View
    {
        $this->authorizeConfiguration($request);
        $progress = $this->workflows->resolve($request);
        $completed = collect($progress->completed_steps ?? [])->map(fn ($value) => (int) $value);
        abort_unless($completed->contains(1), 403, 'Validez d’abord l’étape Organisation / ONG.');

        $organization = $this->organization($request);
        $missions = $organization->missions()->with('country')->orderBy('name')->get();
        $mission = $progress->flow_type === ConfigurationFlowType::NewMission
            || $request->boolean('new')
            ? null
            : ($request->filled('edit')
                ? $organization->missions()->whereKey($request->input('edit'))->firstOrFail()
                : $missions->first());
        $this->workflows->restoreDraft($request, $progress, 2);

        return view('configuration.index', [
            'activeModule' => 'configuration',
            'activeStep' => 2,
            'completedSteps' => $completed,
            'stepStates' => $this->workflows->states($progress),
            'workflow' => $progress,
            'organization' => $organization,
            'mission' => $mission,
            'missions' => $missions,
            'creatingMission' => $progress->flow_type === ConfigurationFlowType::NewMission
                || $request->boolean('new'),
            'archivedMissions' => $organization->missions()->onlyTrashed()->with('country')->latest('deleted_at')->get(),
            'countries' => Country::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function archive(Request $request, string $mission): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        $organization = $this->organization($request);
        $model = $organization->missions()->findOrFail($mission);
        $model->update(['is_active' => false]);
        $model->delete();
        $progress = $this->workflows->resolve($request);
        if (! $organization->missions()->where('is_active', true)->exists()) {
            $this->workflows->invalidateFrom($progress, 2);
        }
        $this->audit->record($request, 'configuration.mission.archived', $model);

        return redirect()->route(
            'configuration.mission',
            $this->workflows->requestParameters($request, $progress),
        )->with('success', 'Mission archivée.');
    }

    public function restore(Request $request, string $mission): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        $organization = $this->organization($request);
        $model = $organization->missions()->onlyTrashed()->findOrFail($mission);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'configuration.mission.restored', $model);

        $progress = $this->workflows->resolve($request);

        return redirect()->route(
            'configuration.mission',
            $this->workflows->requestParameters($request, $progress),
        )
            ->with('success', 'Mission restaurée. Vérifiez-la puis validez de nouveau l’étape.');
    }

    public function save(Request $request): RedirectResponse
    {
        $this->authorizeConfiguration($request);
        $progress = $this->workflows->resolve($request);
        abort_unless(
            collect($progress->completed_steps ?? [])->map(fn ($value) => (int) $value)->contains(1),
            403,
            'Validez d’abord l’étape Organisation / ONG.',
        );

        $organization = $this->organization($request);
        $mission = $progress->flow_type === ConfigurationFlowType::NewMission
            || $request->boolean('create_new')
            ? null
            : ($request->filled('mission_id')
                ? $organization->missions()->whereKey($request->input('mission_id'))->firstOrFail()
                : $organization->missions()->orderBy('created_at')->first());
        $data = $request->validate([
            'mission_id' => ['nullable', 'uuid'],
            'create_new' => ['nullable', 'boolean'],
            'name' => ['required', 'string', 'max:160'],
            'code' => [
                'required', 'alpha_dash', 'max:40',
                Rule::unique('missions', 'code')
                    ->where('organization_id', $organization->id)
                    ->ignore($mission?->id),
            ],
            'country_id' => [
                'required', 'uuid',
                Rule::exists('countries', 'id')->where('is_active', true),
            ],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'action' => ['nullable', Rule::in(['save', 'continue'])],
        ], [
            'name.required' => 'Le nom de la mission est obligatoire.',
            'code.required' => 'Le code de la mission est obligatoire.',
            'code.alpha_dash' => 'Le code accepte uniquement les lettres, chiffres, tirets et tirets bas.',
            'code.unique' => 'Ce code de mission est déjà utilisé dans cette organisation.',
            'country_id.required' => 'Sélectionnez le pays de la mission.',
            'country_id.exists' => 'Le pays sélectionné est invalide ou inactif.',
            'starts_on.date' => 'La date de début est invalide.',
            'ends_on.date' => 'La date de fin est invalide.',
            'ends_on.after_or_equal' => 'La date de fin doit être postérieure ou égale à la date de début.',
        ]);
        if (($data['action'] ?? 'save') === 'continue' && $data['status'] !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Activez la mission avant de passer à l’étape Projets.',
            ]);
        }

        $old = $mission?->only(['country_id', 'code', 'name', 'starts_on', 'ends_on', 'is_active']) ?? [];
        $saved = DB::transaction(function () use ($organization, $mission, $data, $progress): Mission {
            $payload = [
                'country_id' => $data['country_id'],
                'code' => strtoupper($data['code']),
                'name' => trim($data['name']),
                'starts_on' => $data['starts_on'] ?? null,
                'ends_on' => $data['ends_on'] ?? null,
                'is_active' => $data['status'] === 'active',
            ];

            $model = $mission
                ? tap($mission)->update($payload)
                : $organization->missions()->create($payload);

            if ($organization->missions()->where('is_active', true)->exists()) {
                $this->workflows->complete($progress, 2);
            }
            $this->workflows->remember($progress, 'mission_id', $model->id);

            return $model;
        });

        $this->audit->record(
            $request,
            $mission ? 'configuration.mission.updated' : 'configuration.mission.created',
            $saved,
            $old,
            $saved->only(['organization_id', 'country_id', 'code', 'name', 'starts_on', 'ends_on', 'is_active']),
        );

        $target = ($data['action'] ?? 'save') === 'continue'
            ? route('configuration.step', $this->workflows->requestParameters(
                $request,
                $progress,
                ['step' => 'projects'],
            ))
            : route('configuration.mission', $this->workflows->requestParameters($request, $progress));

        return redirect($target)->with('success', 'Mission enregistrée avec succès.');
    }

    private function organization(Request $request): Organization
    {
        $progress = $this->workflows->resolve($request);
        $query = $this->scopes->organizations($request->user());

        return $progress->scope_type === 'organization' && $progress->scope_id
            ? $query->whereKey($progress->scope_id)->firstOrFail()
            : $query->orderBy('created_at')->firstOrFail();
    }

    private function authorizeConfiguration(Request $request): void
    {
        abort_unless($request->user()?->hasPermission('missions.manage'), 403);
    }
}
