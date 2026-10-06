<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** AM-112 — Validation unique Web / API de la configuration médicale d'un projet. */
class SaveProjectMedicalConfigurationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        abort_unless($project instanceof Project, 404);
        // Configuration structurante : Coordination uniquement (cahier des charges).
        Gate::forUser($this->user())->authorize('configure', $project);

        return true;
    }

    protected function prepareForValidation(): void
    {
        // Formulaire Web : pathologies[<id>][] = populations cochées.
        $pathologies = $this->input('pathologies', []);
        if (is_array($pathologies) && ! array_is_list($pathologies)) {
            $this->merge(['pathologies' => collect($pathologies)
                ->map(fn ($populations, $pathologyId) => ['pathology_id' => $pathologyId, 'target_population_ids' => array_values((array) $populations)])
                ->filter(fn ($row) => $row['target_population_ids'] !== [])
                ->values()->all()]);
        }
    }

    public function rules(): array
    {
        return [
            'care_level_ids' => ['required', 'array', 'min:1'],
            'care_level_ids.*' => ['uuid'],
            'target_population_ids' => ['required', 'array', 'min:1'],
            'target_population_ids.*' => ['uuid'],
            'pathologies' => ['nullable', 'array'],
            'pathologies.*.pathology_id' => ['required', 'uuid'],
            'pathologies.*.target_population_ids' => ['required', 'array', 'min:1'],
            'pathologies.*.target_population_ids.*' => ['uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'care_level_ids' => 'niveaux de soins',
            'target_population_ids' => 'populations cibles',
            'pathologies' => 'pathologies',
        ];
    }
}
