<?php

namespace App\Http\Requests;

use App\Models\HealthFacility;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Niveau 2 — Étape 4 de l'assistant (Web et mobile) : paramètres d'approvisionnement. */
class SaveProjectWizardSupplyRequest extends FormRequest
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
            'order_period_months' => [$required, 'integer', 'min:1', 'max:12'],
            'delivery_lead_time_months' => [$required, 'integer', 'min:1', 'max:12'],
            'safety_stock_months' => [$required, 'numeric', Rule::in(HealthFacility::SAFETY_STOCK_OPTIONS)],
            'inventory_date' => ['nullable', 'date'],
            'order_submission_date' => ['nullable', 'date'],
            'order_receipt_date' => ['nullable', 'date', 'after_or_equal:order_submission_date'],
        ];
    }

    public function messages(): array
    {
        return [
            'safety_stock_months.in' => 'Le stock de sécurité doit être de 0,25 ; 0,5 ; 0,75 ; 1 ; 1,5 ou 2 mois.',
        ];
    }

    public function attributes(): array
    {
        return [
            'order_period_months' => 'périodicité de commande',
            'delivery_lead_time_months' => 'délai de livraison',
            'safety_stock_months' => 'stock de sécurité',
            'order_receipt_date' => 'date de réception de commande',
            'order_submission_date' => 'date de soumission de commande',
        ];
    }
}
