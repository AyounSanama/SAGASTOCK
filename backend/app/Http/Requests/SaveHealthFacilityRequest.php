<?php

namespace App\Http\Requests;

use App\Models\CatalogReference;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\GovernanceService;
use App\Services\HealthFacilityConfigurationService;
use App\Services\UserScopeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Support\Facades\Gate;

/**
 * AM-162 (lot c1) — Création et modification d'une FOSA : mêmes règles sur le
 * Web et l'API. Pour l'Admin Projet, le niveau de soins, la catégorie, les
 * populations et les pathologies sont obligatoires et choisis UNIQUEMENT dans
 * la configuration validée par la Coordination.
 */
class SaveHealthFacilityRequest extends FormRequest
{
    private ?Project $resolvedProject = null;

    public function authorize(): bool
    {
        $user = $this->user();
        abort_unless($user && ($user->hasPermission('structures.manage') || $user->hasPermission('health_facilities.manage')), 403);
        $scopes = app(UserScopeService::class);
        abort_unless($scopes->organizations($user)->whereKey($this->organization()->id)->exists(), 404);
        $facility = $this->facility();
        if ($facility) {
            abort_unless($facility->organization_id === $this->organization()->id, 404);
            Gate::forUser($user)->authorize('update', $facility);
        }

        return true;
    }

    public function isProjectAdmin(): bool
    {
        return app(GovernanceService::class)->roleCode($this->user()) === GovernanceService::PROJECT_ADMIN;
    }

    protected function prepareForValidation(): void
    {
        foreach (['target_population_ids', 'pathology_ids'] as $key) {
            if ($this->has($key) && in_array($this->input($key), ['', null], true)) {
                $this->merge([$key => []]);
            }
        }
    }

    public function rules(): array
    {
        $organization = $this->organization();
        $v1 = $this->isProjectAdmin();
        $required = $v1 ? 'required' : 'nullable';

        return [
            'mission_id' => ['nullable', 'uuid', 'exists:missions,id'],
            'project_ids' => ['nullable', 'array'], 'project_ids.*' => ['uuid', 'exists:projects,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('health_facilities')->where('organization_id', $organization->id)->ignore($this->facility()?->id)],
            'name' => ['required', 'string', 'max:180'],
            // L'Admin Projet choisit une catégorie ; le type technique en est déduit.
            'facility_type' => [$v1 ? 'nullable' : 'required', Rule::in(['hospital', 'health_center', 'clinic', 'warehouse', 'community', 'other'])],
            'care_level' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'],
            'region' => ['nullable', 'string', 'max:120'], 'district' => ['nullable', 'string', 'max:120'],
            'locality' => ['nullable', 'string', 'max:190'], 'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
            'care_level_id' => [$required, 'uuid'],
            'facility_category_id' => [$required, 'uuid'],
            'target_population_ids' => [$required, 'array', $v1 ? 'min:1' : null],
            'target_population_ids.*' => ['uuid', 'distinct'],
            'pathology_ids' => [$required, 'array', $v1 ? 'min:1' : null],
            'pathology_ids.*' => ['uuid', 'distinct'],
            'order_period_months' => ['nullable', 'integer', 'between:1,12'],
            'delivery_lead_time_months' => ['nullable', 'integer', 'between:1,12'],
            'safety_stock_months' => ['nullable', 'numeric', Rule::in(HealthFacility::SAFETY_STOCK_OPTIONS)],
            'inventory_date' => ['nullable', 'date'],
            'order_submission_date' => ['nullable', 'date'],
            'order_receipt_date' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nom de la FOSA', 'code' => 'code FOSA', 'care_level_id' => 'niveau de soins',
            'facility_category_id' => 'catégorie de FOSA', 'target_population_ids' => 'population cible',
            'pathology_ids' => 'pathologies / activités', 'order_period_months' => 'périodicité de commande',
            'delivery_lead_time_months' => 'délai de livraison', 'safety_stock_months' => 'stock de sécurité',
            'inventory_date' => 'date d’inventaire', 'order_submission_date' => 'date de soumission de commande',
            'order_receipt_date' => 'date de réception de commande',
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Le champ « :attribute » est obligatoire.',
            'safety_stock_months.in' => 'Le stock de sécurité doit être de 0,25 ; 0,5 ; 0,75 ; 1 ; 1,5 ou 2 mois.',
            'target_population_ids.min' => 'Sélectionnez au moins une population cible.',
            'pathology_ids.min' => 'Sélectionnez au moins une pathologie ou activité.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $organization = $this->organization();
            if ($this->filled('mission_id') && ! Mission::whereKey($this->input('mission_id'))->where('organization_id', $organization->id)->exists()) {
                $validator->errors()->add('mission_id', 'Cette mission ne fait pas partie de l’organisation.');
            }
            $project = $this->project();
            $needsConfiguration = $this->filled('care_level_id') || $this->filled('target_population_ids') || $this->filled('pathology_ids');
            if (! $needsConfiguration) {
                return;
            }
            if (! $project) {
                $validator->errors()->add('project_ids', 'Rattachez la FOSA à un projet configuré pour choisir son niveau de soins, ses populations et ses pathologies.');

                return;
            }
            $options = app(HealthFacilityConfigurationService::class)->options($project);
            $outside = fn (string $field, $allowed) => collect((array) $this->input($field))->filter()->diff($allowed)->isNotEmpty();
            if ($this->filled('care_level_id') && $outside('care_level_id', $options['care_levels']->pluck('id'))) {
                $validator->errors()->add('care_level_id', 'Choisissez un niveau de soins validé par la Coordination pour ce projet.');
            }
            if ($this->filled('facility_category_id') && $outside('facility_category_id', $options['facility_categories']->pluck('id'))) {
                $validator->errors()->add('facility_category_id', 'Choisissez une catégorie de FOSA de la liste de référence.');
            }
            if ($outside('target_population_ids', $options['target_populations']->pluck('id'))) {
                $validator->errors()->add('target_population_ids', 'Choisissez uniquement des populations validées par la Coordination pour ce projet.');
            }
            if ($outside('pathology_ids', $options['pathologies']->pluck('id'))) {
                $validator->errors()->add('pathology_ids', 'Choisissez uniquement des pathologies validées par la Coordination pour ce projet.');
            }
        });
    }

    /** Données de la FOSA (hors configuration V1). */
    public function facilityData(): array
    {
        $data = collect($this->validated())->except([
            'target_population_ids', 'pathology_ids', ...HealthFacility::SUPPLY_FIELDS,
        ])->all();
        $project = $this->project();
        if ($this->isProjectAdmin()) {
            $data['project_ids'] = [$project->id];
            $data['mission_id'] = $project->mission_id;
        }
        if (empty($data['facility_type'])) {
            $data['facility_type'] = $this->facility()?->facility_type ?? $this->facilityTypeFromCategory($data['facility_category_id'] ?? null);
        }
        if (! $this->facility() && ! array_key_exists('is_active', $data)) {
            $data['is_active'] = true;
        }

        return $data;
    }

    /** Configuration V1 (populations, pathologies, paramètres d'approvisionnement). */
    public function configurationData(): array
    {
        $validated = $this->validated();

        return collect(['target_population_ids', 'pathology_ids', ...HealthFacility::SUPPLY_FIELDS])
            ->filter(fn (string $field) => array_key_exists($field, $validated))
            ->mapWithKeys(fn (string $field) => [$field => $validated[$field]])
            ->all();
    }

    public function project(): ?Project
    {
        if ($this->resolvedProject) {
            return $this->resolvedProject;
        }
        $scopes = app(UserScopeService::class);
        if ($this->isProjectAdmin()) {
            $ids = $scopes->directProjectIds($this->user())->unique()->values();
            abort_unless($ids->count() === 1, 403, 'Le compte Admin Projet doit être rattaché à un projet unique.');

            return $this->resolvedProject = Project::whereKey($ids->first())->where('organization_id', $this->organization()->id)->firstOrFail();
        }
        $ids = collect($this->input('project_ids', []))->filter()->unique();
        if ($ids->isNotEmpty()) {
            $allowed = $scopes->projectIds($this->user());
            abort_unless(
                Project::whereIn('id', $ids)->where('organization_id', $this->organization()->id)->count() === $ids->count()
                    && $ids->every(fn ($id) => $allowed->contains($id)),
                422,
                'Un projet sélectionné ne fait pas partie de votre périmètre.',
            );
            if ($ids->count() === 1) {
                return $this->resolvedProject = Project::find($ids->first());
            }
        }

        return $this->resolvedProject = $this->facility()?->projects()->first();
    }

    public function organization(): Organization
    {
        $organization = $this->route('organization');

        return $organization instanceof Organization ? $organization : Organization::findOrFail($organization);
    }

    public function facility(): ?HealthFacility
    {
        $facility = $this->route('facility');

        return $facility instanceof HealthFacility ? $facility : ($facility ? HealthFacility::find($facility) : null);
    }

    private function facilityTypeFromCategory(?string $categoryId): string
    {
        $code = $categoryId ? CatalogReference::whereKey($categoryId)->value('code') : null;

        return match ($code) {
            'CAT-HR', 'CAT-HD' => 'hospital',
            'CAT-CMA' => 'clinic',
            default => 'health_center',
        };
    }
}
