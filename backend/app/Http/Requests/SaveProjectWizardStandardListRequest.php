<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Niveau 2 — Étape 3 de l'assistant (Web et mobile) : choix médicaux et produits retenus. */
class SaveProjectWizardStandardListRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');
        abort_unless($project instanceof Project, 404);
        Gate::forUser($this->user())->authorize('configure', $project);

        return $this->user()->hasPermission('projects.manage');
    }

    public function isDraft(): bool
    {
        return $this->input('intent') === 'draft';
    }

    public function rules(): array
    {
        $required = $this->isDraft() ? 'nullable' : 'required';

        return [
            'care_level_ids' => [$required, 'array'],
            'care_level_ids.*' => ['uuid'],
            'target_population_ids' => [$required, 'array'],
            'target_population_ids.*' => ['uuid'],
            'pathology_ids' => ['nullable', 'array'],
            'pathology_ids.*' => ['uuid'],
            'retained' => ['nullable', 'array'],
            'retained.*' => ['uuid'],
            'added' => ['nullable', 'array'],
            'added.*' => ['uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'care_level_ids.required' => 'Sélectionnez au moins un niveau de soins.',
            'target_population_ids.required' => 'Sélectionnez au moins une population cible.',
        ];
    }
}
