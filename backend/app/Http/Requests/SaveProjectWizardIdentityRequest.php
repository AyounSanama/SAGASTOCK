<?php

namespace App\Http\Requests;

use App\Models\Mission;
use App\Models\Project;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Niveau 2 — Étapes 1 et 2 de l'assistant « Créer un projet / programme » :
 * type, mission, organisation, bailleur, code et intitulé.
 */
class SaveProjectWizardIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        if ($project instanceof Project) {
            Gate::forUser($this->user())->authorize('configure', $project);
        }

        return app(GovernanceService::class)->roleCode($this->user()) === GovernanceService::COORDINATION_ADMIN
            && $this->user()->hasPermission('projects.manage');
    }

    public function rules(): array
    {
        $missionIds = app(UserScopeService::class)->coordinationMissionIds($this->user());
        $project = $this->route('project') instanceof Project ? $this->route('project') : null;
        $organizationId = Mission::whereIn('id', $missionIds)->whereKey($this->input('mission_id'))->value('organization_id');

        return [
            'type' => ['required', Rule::in(array_keys(Project::TYPES))],
            'mission_id' => ['required', 'uuid', Rule::in($missionIds->all())],
            'implementing_partner' => ['required', 'string', 'max:190'],
            // Projet bailleur : un bailleur ; programme national : bailleur facultatif.
            'donor_id' => ['nullable', 'required_if:type,donor_project', 'uuid', Rule::exists('donors', 'id')->where('organization_id', $organizationId)->whereNull('deleted_at')],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('projects')->where('organization_id', $organizationId)->ignore($project?->id)],
            'name' => ['required', 'string', 'max:180'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }

    public function messages(): array
    {
        return [
            'donor_id.required_if' => 'Sélectionnez le bailleur du projet.',
            'mission_id.in' => 'Choisissez une mission de votre coordination.',
            'code.unique' => 'Ce code est déjà utilisé par un autre projet de l’organisation.',
            'code.alpha_dash' => 'Le code ne contient que des lettres, chiffres, tirets et tirets bas (ex. GFFO5).',
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'type',
            'mission_id' => 'mission (pays)',
            'implementing_partner' => 'organisation ou programme',
            'donor_id' => 'bailleur',
            'code' => 'code bailleur / programme MoH',
            'name' => 'intitulé du projet',
            'starts_on' => 'date de début',
            'ends_on' => 'date de fin',
        ];
    }
}
