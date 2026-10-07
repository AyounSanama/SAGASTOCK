<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Niveau 7 — Origine du stock : couple ONG/Bailleur ou « Autre ».
 *
 * Le couple ONG/Bailleur correspond à un projet (ou programme) de la FOSA :
 * l'ONG qui le met en œuvre et son bailleur (ex. « MDM / GFFO5 »). « Autre » :
 * produit livré par un tiers, dont le nom est obligatoire. Web et API
 * passent par ce service (mêmes choix, mêmes règles).
 */
class StockOriginService
{
    public const TYPE_PROJECT = 'project';

    public const TYPE_OTHER = 'other';

    /** Règles de saisie de l'origine d'une entrée (Web et API). */
    public static function rules(): array
    {
        return [
            'origin_type' => ['required', Rule::in([self::TYPE_PROJECT, self::TYPE_OTHER])],
            'origin_project_id' => ['nullable', 'uuid', 'required_if:origin_type,'.self::TYPE_PROJECT],
            'origin_label' => ['nullable', 'string', 'max:190', 'required_if:origin_type,'.self::TYPE_OTHER],
        ];
    }

    /** Libellés des champs pour les messages d'erreur. */
    public static function attributes(): array
    {
        return ['origin_type' => 'origine', 'origin_project_id' => 'couple ONG/Bailleur', 'origin_label' => 'nom du fournisseur tiers'];
    }

    /** Messages propres à l'origine. */
    public static function messages(): array
    {
        return [
            'origin_type.required' => 'Choisissez l’origine de l’entrée : le couple ONG/Bailleur ou « Autre ».',
            'origin_project_id.required_if' => 'Choisissez le couple ONG/Bailleur.',
            'origin_label.required_if' => 'Indiquez qui a livré ces produits (origine « Autre »).',
        ];
    }

    /**
     * Couples ONG/Bailleur proposés pour un site : les projets de sa FOSA.
     *
     * @return Collection<int, array{type: string, project_id: string, label: string}>
     */
    public function options(Site $site): Collection
    {
        $facility = $site->healthFacility;
        if (! $facility) {
            return collect();
        }

        return $facility->projects()->with(['organization:id,name', 'donors:id,name'])
            ->whereIn('projects.status', ['active', 'draft'])->orderBy('projects.code')->get()
            ->map(fn (Project $project) => [
                'type' => self::TYPE_PROJECT,
                'project_id' => $project->id,
                'label' => $this->label($project),
            ])->values();
    }

    /** « ONG Santé / Bailleur A · GFFO5 » ; programme national sans bailleur : « ONG / Programme national · PNLT ». */
    public function label(?Project $project, ?string $otherLabel = null): string
    {
        if (! $project) {
            return 'Autre'.($otherLabel ? ' · '.$otherLabel : '');
        }
        $ngo = $project->implementing_partner ?: $project->organization?->name;
        $donor = $project->donors->first()?->name ?? ($project->type === 'national_program' ? 'Programme national' : 'Bailleur non renseigné');

        return trim(($ngo ? $ngo.' / ' : '').$donor.' · '.$project->code);
    }

    /**
     * Origine validée pour une entrée sur ce site.
     *
     * @return array{origin_type: string, origin_project_id: ?string, origin_label: ?string, origin_key: string}
     */
    public function resolve(Site $site, array $data): array
    {
        if (($data['origin_type'] ?? null) === self::TYPE_OTHER) {
            $label = Str::squish((string) $data['origin_label']);

            return ['origin_type' => self::TYPE_OTHER, 'origin_project_id' => null, 'origin_label' => $label,
                'origin_key' => 'other:'.Str::limit(mb_strtolower($label), 110, '')];
        }
        $project = $this->options($site)->firstWhere('project_id', $data['origin_project_id'] ?? null);
        if (! $project) {
            throw ValidationException::withMessages(['origin_project_id' => 'Ce couple ONG/Bailleur ne finance pas cette FOSA.']);
        }

        return ['origin_type' => self::TYPE_PROJECT, 'origin_project_id' => $project['project_id'], 'origin_label' => null,
            'origin_key' => 'project:'.$project['project_id']];
    }

    /** Lot de cette origine (un même numéro reçu de deux origines donne deux lots). */
    public function batch(Organization $organization, string $productId, string $batchNumber, array $origin): Batch
    {
        $batch = Batch::withTrashed()->firstOrNew([
            'organization_id' => $organization->id,
            'product_id' => $productId,
            'batch_number' => trim($batchNumber),
            'origin_key' => $origin['origin_key'],
        ]);
        if ($batch->exists && $batch->trashed()) {
            $batch->restore();
        }
        $batch->fill([
            'origin_type' => $origin['origin_type'],
            'origin_project_id' => $origin['origin_project_id'],
            'origin' => $origin['origin_label'] ?? $batch->origin,
        ]);

        return $batch;
    }
}
