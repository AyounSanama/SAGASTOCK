<?php

namespace App\Http\Requests;

use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation unique de création et de modification d'un projet.
 *
 * Utilisée par le portail Web et par l'API (mobile/tablette) : mêmes champs,
 * mêmes règles, mêmes messages, quel que soit le client.
 */
class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        abort_unless(
            app(UserScopeService::class)->organizations($this->user())->whereKey($this->organization()->id)->exists(),
            404,
        );
        $project = $this->route('project');
        if ($project instanceof Project) {
            abort_unless($project->organization_id === $this->organization()->id, 404);
            abort_unless(app(UserScopeService::class)->projects($this->user())->whereKey($project->id)->exists(), 404);
        }

        return (bool) $this->user()?->hasPermission('projects.manage');
    }

    protected function prepareForValidation(): void
    {
        // Compatibilité : les anciens clients n'envoient que is_active.
        if (! $this->filled('status')) {
            $this->merge(['status' => $this->has('is_active') && ! $this->boolean('is_active') ? 'suspended' : 'active']);
        }
        foreach (['admin', 'donor_ids', 'program_ids'] as $key) {
            if ($this->has($key) && in_array($this->input($key), ['', null], true)) {
                $this->request->remove($key);
            }
        }
        // Section « Admin Projet » facultative : un bloc entièrement vide est ignoré.
        $admin = $this->input('admin');
        if (is_array($admin) && collect($admin)->filter(fn ($value) => filled($value))->isEmpty()) {
            $this->request->remove('admin');
        }
    }

    public function rules(): array
    {
        $organization = $this->organization();
        $project = $this->route('project') instanceof Project ? $this->route('project') : null;

        return [
            'mission_id' => ['required', 'uuid', 'exists:missions,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('projects')->where('organization_id', $organization->id)->ignore($project?->id)],
            'name' => ['required', 'string', 'max:180'],
            'implementing_partner' => ['nullable', 'string', 'max:190'],
            'donor_reference_code' => ['nullable', 'string', 'max:120'],
            'moh_program_code' => ['nullable', 'string', 'max:120'],
            'responsible_name' => ['nullable', 'string', 'max:160'],
            'responsible_contact' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            // AM-114 / DEC-06 : un projet actif doit porter ses trois paramètres d'approvisionnement.
            'order_period_months' => ['nullable', 'required_if:status,active', 'integer', 'min:1', 'max:24'],
            'delivery_lead_time_months' => ['nullable', 'required_if:status,active', 'integer', 'min:1', 'max:24'],
            'safety_stock_months' => ['nullable', 'required_if:status,active', 'integer', 'min:1', 'max:24'],
            'status' => ['required', Rule::in(array_keys(Project::STATUSES))],
            'donor_ids' => ['nullable', 'array', 'max:1'],
            'donor_ids.*' => ['uuid', Rule::exists('donors', 'id')->where('organization_id', $organization->id)],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['uuid', Rule::exists('programs', 'id')->where('organization_id', $organization->id)],
            ...($project ? [] : $this->adminRules()),
        ];
    }

    public function messages(): array
    {
        return [
            'donor_ids.max' => 'Un projet est rattaché à un seul bailleur : créez un projet par code bailleur.',
            'required_if' => 'Le champ :attribute est obligatoire pour un projet actif.',
            'status.in' => 'Le statut doit être Brouillon, Actif, Suspendu ou Clôturé.',
        ];
    }

    public function attributes(): array
    {
        return [
            'implementing_partner' => 'organisation / programme de mise en œuvre',
            'donor_reference_code' => 'code bailleur',
            'moh_program_code' => 'code programme MoH',
            'responsible_name' => 'responsable du projet',
            'responsible_contact' => 'contact du responsable',
            'status' => 'statut',
            'order_period_months' => 'périodicité de commande',
            'delivery_lead_time_months' => 'délai de livraison',
            'safety_stock_months' => 'stock de sécurité',
        ];
    }

    protected function passedValidation(): void
    {
        $organization = $this->organization();
        $mission = Mission::findOrFail($this->input('mission_id'));
        abort_unless($mission->organization_id === $organization->id, 422, 'La mission ne dépend pas de cette organisation.');
        if (app(GovernanceService::class)->roleCode($this->user()) === GovernanceService::COORDINATION_ADMIN) {
            abort_unless(app(UserScopeService::class)->coordinationMissionIds($this->user())->contains($mission->id), 403);
        }
    }

    /** Données prêtes pour ProjectProvisioningService. */
    public function projectData(): array
    {
        $data = $this->validated();
        $data['is_active'] = $data['status'] === 'active';

        return $data;
    }

    private function adminRules(): array
    {
        return [
            'admin' => ['nullable', 'array'],
            'admin.first_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.last_name' => ['required_with:admin', 'string', 'max:80'],
            'admin.email' => ['required_with:admin', 'email', 'max:190', 'unique:users,email'],
            'admin.phone' => ['nullable', 'string', 'max:40'],
            'admin.username' => ['nullable', 'alpha_dash', 'max:80', 'unique:users,username'],
            'admin.password' => ['required_with:admin', 'confirmed', PasswordPolicy::rule()],
        ];
    }

    private function organization(): Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : Organization::findOrFail($organization);
    }
}
