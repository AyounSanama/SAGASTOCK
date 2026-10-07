<?php

namespace App\Services;

use App\Models\CatalogReference;
use App\Models\HealthFacility;
use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductCode;
use App\Models\ProductStandardMapping;
use App\Models\Project;
use App\Models\StandardList;
use App\Models\StandardListVersion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Niveau 6 — Listes Standard gérées par la Coordination.
 *
 * - Codification propre à l'ONG : ajout d'un produit (code, désignation,
 *   conditionnement) et import Excel de la liste standard totale
 *   (produit × niveau de soins × population × pathologie / activité × catégorie).
 * - Lien code-barres ↔ produit.
 * - Décochage d'articles par FOSA.
 *
 * Web et API passent par ce service : mêmes règles partout. Seule la
 * Coordination (permission standard_lists.manage) y accède.
 */
class StandardListCatalogService
{
    /** Colonnes du modèle Excel, dans l'ordre. */
    public const TEMPLATE_HEADERS = [
        'code' => 'Code',
        'name' => 'Désignation',
        'packaging' => 'Conditionnement',
        'care_level' => 'Niveau de soins',
        'population' => 'Population',
        'activity' => 'Pathologie / activité',
        'facility_category' => 'Catégorie FOSA',
        'barcode' => 'Code-barres',
    ];

    /** Intitulés acceptés pour chaque colonne (sans accents, minuscules). */
    private const HEADER_ALIASES = [
        'code' => ['code', 'code produit', 'product code'],
        'name' => ['designation', 'nom', 'name', 'produit', 'product'],
        'packaging' => ['conditionnement', 'packaging'],
        'care_level' => ['niveau de soins', 'niveau', 'care level', 'care_level'],
        'population' => ['population', 'population cible', 'target population'],
        'activity' => ['pathologie / activite', 'pathologie', 'activite', 'pathology', 'examen', 'examen de laboratoire'],
        'facility_category' => ['categorie fosa', 'categorie', 'facility category'],
        'barcode' => ['code-barres', 'code barres', 'code-barre', 'barcode', 'gtin'],
    ];

    private const MAX_REPORTED_ERRORS = 20;

    /**
     * Règles d'un nouveau produit (Web et API), préfixe éventuel « new_product. ».
     *
     * @return array{0: array, 1: array, 2: array}
     */
    public static function productRules(string $prefix = ''): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\/-]+$/'],
            'name' => ['required', 'string', 'max:190'],
            'packaging' => ['nullable', 'string', 'max:190'],
            'barcode' => ['nullable', 'string', 'max:190'],
            'care_level_id' => ['nullable', 'uuid'],
            'activity_id' => ['nullable', 'uuid'],
            'target_population_id' => ['nullable', 'uuid'],
        ];
        $labels = ['code' => 'code', 'name' => 'désignation', 'packaging' => 'conditionnement', 'barcode' => 'code-barres',
            'care_level_id' => 'niveau de soins', 'activity_id' => 'pathologie / activité', 'target_population_id' => 'population'];
        $key = fn (string $field) => $prefix.$field;

        return [
            collect($rules)->mapWithKeys(fn ($rule, $field) => [$key($field) => $rule])->all(),
            [$key('code').'.regex' => 'Le code ne contient que des lettres, chiffres, points, tirets, barres obliques et tirets bas.'],
            collect($labels)->mapWithKeys(fn ($label, $field) => [$key($field) => $label])->all(),
        ];
    }

    /** Message de fin d'import, identique Web et API. */
    public static function importMessage(array $result): string
    {
        $message = $result['products'].' produit(s) importé(s), dont '.$result['created'].' nouveau(x), et '.$result['mappings'].' correspondance(s) ajoutée(s).';

        return $result['errors'] === [] ? $message : $message.' Certaines lignes ont été ignorées : voir le détail.';
    }

    /**
     * « + Ajouter un produit » : nouveau produit de l'organisation et, si un
     * niveau de soins est donné, sa correspondance pour la génération.
     *
     * @param  array{code: string, name: string, packaging?: ?string, barcode?: ?string, care_level_id?: ?string, activity_id?: ?string, target_population_id?: ?string}  $data
     */
    public function createProduct(Organization $organization, array $data): Product
    {
        $code = trim($data['code']);
        if (Product::withTrashed()->where('organization_id', $organization->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'Ce code produit existe déjà dans le catalogue de l’organisation.']);
        }
        $barcode = $this->cleanBarcode($data['barcode'] ?? null);
        $this->assertBarcodeFree($barcode, null);
        $careLevel = $this->reference($organization, ['care_level'], $data['care_level_id'] ?? null, 'care_level_id');
        $activity = $this->reference($organization, StandardListGenerationService::ACTIVITY_TYPES, $data['activity_id'] ?? null, 'activity_id');
        $population = $this->reference($organization, ['target_population'], $data['target_population_id'] ?? null, 'target_population_id');

        return DB::transaction(function () use ($organization, $code, $data, $barcode, $careLevel, $activity, $population): Product {
            $product = $organization->products()->create([
                'code' => $code,
                'name' => trim($data['name']),
                'packaging' => blank($data['packaging'] ?? null) ? null : trim($data['packaging']),
                'product_type' => 'medicine',
                'is_active' => true,
            ]);
            if ($barcode) {
                $product->codes()->create(['code_type' => 'barcode', 'value' => $barcode, 'is_primary' => true]);
            }
            if ($careLevel) {
                $this->map($organization, $product, $careLevel, $population, $activity, null);
            }

            return $product;
        });
    }

    /**
     * « Importer depuis Excel » : liste standard totale de l'organisation.
     * Produits créés ou mis à jour par code ; correspondances ajoutées ; une
     * pathologie inconnue est ajoutée au référentiel de l'organisation.
     *
     * @return array{products: int, created: int, mappings: int, product_ids: list<string>, errors: list<string>}
     */
    public function import(Organization $organization, string $path): array
    {
        try {
            $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['file' => 'Le fichier n’a pas pu être lu. Utilisez le modèle Excel (.xlsx).']);
        }
        $columns = $this->columns(array_shift($rows) ?: []);
        if (! isset($columns['code'], $columns['name'])) {
            throw ValidationException::withMessages(['file' => 'Colonnes obligatoires : « Code » et « Désignation ». Téléchargez le modèle Excel.']);
        }

        $references = $this->referenceIndex($organization);
        $result = ['products' => 0, 'created' => 0, 'mappings' => 0, 'product_ids' => [], 'errors' => []];
        $errors = 0;

        DB::transaction(function () use ($organization, $rows, $columns, &$references, &$result, &$errors): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $value = fn (string $key) => isset($columns[$key]) ? trim((string) ($row[$columns[$key]] ?? '')) : '';
                $code = $value('code');
                $name = $value('name');
                if ($code === '' && $name === '') {
                    continue;
                }
                $error = function (string $message) use ($line, &$result, &$errors): void {
                    $errors++;
                    if (count($result['errors']) < self::MAX_REPORTED_ERRORS) {
                        $result['errors'][] = "Ligne $line : $message";
                    }
                };
                if ($code === '' || $name === '') {
                    $error('code et désignation obligatoires.');

                    continue;
                }

                $careLevel = $this->find($references, 'care_level', $value('care_level'));
                if ($value('care_level') !== '' && ! $careLevel) {
                    $error('niveau de soins « '.$value('care_level').' » inconnu (ajoutez-le dans Référentiels).');

                    continue;
                }
                $population = $this->find($references, 'target_population', $value('population'));
                if ($value('population') !== '' && ! $population) {
                    $error('population « '.$value('population').' » inconnue.');

                    continue;
                }
                $category = $this->find($references, 'facility_category', $value('facility_category'));
                if ($value('facility_category') !== '' && ! $category) {
                    $error('catégorie de FOSA « '.$value('facility_category').' » inconnue.');

                    continue;
                }
                $barcode = $this->cleanBarcode($value('barcode'));
                $product = Product::withTrashed()->where('organization_id', $organization->id)->where('code', $code)->first();
                if ($barcode && ProductCode::where('value', $barcode)->where('code_type', 'barcode')
                    ->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))->exists()) {
                    $error("code-barres $barcode déjà attribué à un autre produit.");

                    continue;
                }

                $activity = null;
                if ($value('activity') !== '') {
                    $activity = $this->find($references, StandardListGenerationService::ACTIVITY_TYPES, $value('activity'))
                        ?? $this->createActivity($organization, $value('activity'), $careLevel, $references);
                }

                $attributes = ['name' => $name, 'is_active' => true];
                if ($value('packaging') !== '') {
                    $attributes['packaging'] = $value('packaging');
                }
                if ($product) {
                    if ($product->trashed()) {
                        $product->restore();
                    }
                    $product->update($attributes);
                } else {
                    $product = $organization->products()->create([...$attributes, 'code' => $code, 'product_type' => 'medicine']);
                    $result['created']++;
                }
                if ($barcode) {
                    $product->codes()->updateOrCreate(['code_type' => 'barcode'], ['value' => $barcode, 'is_primary' => true]);
                }
                if ($careLevel && $this->map($organization, $product, $careLevel, $population, $activity, $category)) {
                    $result['mappings']++;
                }
                if (! in_array($product->id, $result['product_ids'], true)) {
                    $result['product_ids'][] = $product->id;
                    $result['products']++;
                }
            }
        });

        if ($errors > count($result['errors'])) {
            $result['errors'][] = 'Et '.($errors - count($result['errors'])).' autre(s) ligne(s) ignorée(s).';
        }

        return $result;
    }

    /** Modèle Excel à remplir, avec un exemple. */
    public function template(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Liste Standard');
        $sheet->fromArray(array_values(self::TEMPLATE_HEADERS), null, 'A1');
        $sheet->fromArray(['AMOX500', 'Amoxicilline 500 mg', 'Gélule, boîte de 1000', 'Soins de santé primaire', 'Adultes', 'Infections respiratoires', 'Centre de santé intégré (CSI)', ''], null, 'A2');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /**
     * Ajoute des produits à la Liste Standard du projet, hors assistant.
     * Brouillon : complété. Version validée : nouvelle version validée
     * (l'ancienne est conservée comme « remplacée »), pour garder l'historique.
     *
     * @param  list<string>  $productIds
     */
    public function appendToProjectList(Project $project, array $productIds, int $actorId): ?StandardListVersion
    {
        $productIds = Product::where('organization_id', $project->organization_id)->whereIn('id', $productIds)->pluck('id');
        if ($productIds->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($project, $productIds, $actorId): StandardListVersion {
            $list = StandardList::firstOrCreate(
                ['organization_id' => $project->organization_id, 'scope_type' => 'project', 'scope_id' => $project->id],
                ['code' => $project->code.'_STD', 'name' => 'Liste standard · '.$project->name, 'is_active' => true],
            );
            $latest = $list->versions()->whereIn('status', ['draft', 'published'])->orderByDesc('version_number')->first();
            if ($latest?->status === 'draft') {
                $latest->products()->syncWithoutDetaching($productIds->all());

                return $latest;
            }

            $version = $list->versions()->create([
                ...($latest?->only(['care_level_id', 'facility_category_id', 'target_population_ids', 'pathology_ids', 'laboratory_exam_ids']) ?? []),
                'version_number' => ((int) $list->versions()->max('version_number')) + 1,
                'status' => $project->status === 'draft' ? 'draft' : 'published',
                'published_by' => $project->status === 'draft' ? null : $actorId,
                'published_at' => $project->status === 'draft' ? null : now(),
                'change_notes' => 'Produits ajoutés par la Coordination.',
            ]);
            $version->products()->sync(($latest?->products()->pluck('products.id') ?? collect())->concat($productIds)->unique()->values()->all());
            if ($latest && $version->status === 'published') {
                $latest->update(['status' => 'superseded']);
            }

            return $version;
        });
    }

    /** Lien code-barres ↔ produit (vide : lien supprimé). */
    public function setBarcode(Product $product, ?string $value): void
    {
        $barcode = $this->cleanBarcode($value);
        if (! $barcode) {
            $product->codes()->where('code_type', 'barcode')->delete();

            return;
        }
        $this->assertBarcodeFree($barcode, $product);
        $product->codes()->updateOrCreate(['code_type' => 'barcode'], ['value' => $barcode, 'is_primary' => true]);
    }

    /** Produit correspondant à un code-barres scanné, dans l'organisation. */
    public function findByBarcode(Organization $organization, string $value): ?Product
    {
        $barcode = $this->cleanBarcode($value);

        return $barcode ? Product::where('organization_id', $organization->id)->where('is_active', true)
            ->whereHas('codes', fn ($query) => $query->where('code_type', 'barcode')->where('value', $barcode))
            ->first() : null;
    }

    /**
     * Décochage par FOSA : seuls les produits de la liste du projet comptent ;
     * $retainedIds sont gardés, les autres sont retirés pour cette FOSA.
     *
     * @param  list<string>  $retainedIds
     * @return array{excluded: list<string>, restored: list<string>}
     */
    public function saveFacilitySelection(HealthFacility $facility, array $retainedIds, ?User $actor): array
    {
        $listed = $this->facilityCandidateIds($facility);
        $retained = collect($retainedIds)->intersect($listed);
        $wanted = $listed->diff($retained)->values();
        $current = $this->excludedIds($facility);
        $add = $wanted->diff($current)->values();
        $remove = $current->intersect($listed)->diff($wanted)->values();

        DB::transaction(function () use ($facility, $add, $remove, $actor): void {
            DB::table('health_facility_product_exclusions')->where('health_facility_id', $facility->id)->whereIn('product_id', $remove)->delete();
            $now = now();
            DB::table('health_facility_product_exclusions')->insert($add->map(fn (string $productId) => [
                'id' => (string) Str::uuid(), 'health_facility_id' => $facility->id, 'product_id' => $productId,
                'excluded_by' => $actor?->id, 'created_at' => $now, 'updated_at' => $now,
            ])->all());
        });

        return ['excluded' => $add->all(), 'restored' => $remove->all()];
    }

    /** @return Collection<int,string> */
    public function excludedIds(HealthFacility $facility): Collection
    {
        return DB::table('health_facility_product_exclusions')->where('health_facility_id', $facility->id)->pluck('product_id');
    }

    /** Produits de la Liste Standard de la FOSA avant décochage. @return Collection<int,string> */
    public function facilityCandidateIds(HealthFacility $facility): Collection
    {
        return app(HealthFacilityManagementService::class)->standardList($facility, applyExclusions: false)->pluck('id');
    }

    /** Correspondance produit → critères ; vrai si elle vient d'être créée. */
    private function map(Organization $organization, Product $product, CatalogReference $careLevel, ?CatalogReference $population, ?CatalogReference $activity, ?CatalogReference $category): bool
    {
        $mapping = ProductStandardMapping::firstOrCreate([
            'organization_id' => $organization->id,
            'product_id' => $product->id,
            'care_level_id' => $careLevel->id,
            'target_population_id' => $population?->id,
            'pathology_id' => $activity?->reference_type === 'pathology' ? $activity->id : null,
            'laboratory_exam_id' => $activity?->reference_type === 'laboratory_exam' ? $activity->id : null,
            'facility_category_id' => $category?->id,
        ]);

        return $mapping->wasRecentlyCreated;
    }

    /** @param  list<string>  $types */
    private function reference(Organization $organization, array $types, ?string $id, string $field): ?CatalogReference
    {
        if (blank($id)) {
            return null;
        }
        $reference = CatalogReference::whereKey($id)->whereIn('reference_type', $types)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))->first();
        if (! $reference) {
            throw ValidationException::withMessages([$field => 'Valeur inconnue pour cette organisation.']);
        }

        return $reference;
    }

    /** Références visibles de l'organisation, indexées par type puis par nom et code normalisés. */
    private function referenceIndex(Organization $organization): array
    {
        $index = [];
        CatalogReference::whereIn('reference_type', ['care_level', 'target_population', 'facility_category', ...StandardListGenerationService::ACTIVITY_TYPES])
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            // Les références de l'organisation l'emportent sur le référentiel global.
            ->orderByRaw('organization_id is null desc')
            ->get()->each(function (CatalogReference $reference) use (&$index): void {
                foreach ([$reference->name, $reference->code] as $key) {
                    $index[$reference->reference_type][$this->normalize((string) $key)] = $reference;
                }
            });

        return $index;
    }

    /** @param  string|list<string>  $types */
    private function find(array $index, string|array $types, string $value): ?CatalogReference
    {
        if ($value === '') {
            return null;
        }
        foreach ((array) $types as $type) {
            if ($found = $index[$type][$this->normalize($value)] ?? null) {
                return $found;
            }
        }

        return null;
    }

    /** Pathologie (ou examen, sous « Programme Laboratoire ») ajoutée au référentiel de l'organisation. */
    private function createActivity(Organization $organization, string $name, ?CatalogReference $careLevel, array &$index): CatalogReference
    {
        $type = $careLevel && strtoupper((string) $careLevel->code) === 'LAB' ? 'laboratory_exam' : 'pathology';
        $base = Str::upper(Str::limit(Str::slug($name, '_'), 50, ''));
        $code = $base ?: 'ACT';
        for ($i = 2; CatalogReference::where('organization_id', $organization->id)->where('reference_type', $type)->where('code', $code)->exists(); $i++) {
            $code = $base.'_'.$i;
        }
        $reference = $organization->catalogReferences()->create([
            'reference_type' => $type, 'code' => $code, 'name' => $name, 'is_active' => true,
        ]);
        $index[$type][$this->normalize($name)] = $reference;

        return $reference;
    }

    /** @return array<string,int> clé de colonne => position */
    private function columns(array $header): array
    {
        $columns = [];
        foreach ($header as $position => $label) {
            $label = $this->normalize((string) $label);
            foreach (self::HEADER_ALIASES as $key => $aliases) {
                if (! isset($columns[$key]) && in_array($label, $aliases, true)) {
                    $columns[$key] = $position;
                }
            }
        }

        return $columns;
    }

    private function normalize(string $value): string
    {
        return Str::of(Str::ascii($value))->lower()->squish()->toString();
    }

    private function cleanBarcode(?string $value): ?string
    {
        $value = preg_replace('/\s+/', '', (string) $value);

        return $value === '' ? null : Str::limit($value, 190, '');
    }

    private function assertBarcodeFree(?string $barcode, ?Product $product): void
    {
        if ($barcode && ProductCode::where('value', $barcode)->where('code_type', 'barcode')
            ->when($product, fn ($query) => $query->where('product_id', '!=', $product->id))->exists()) {
            throw ValidationException::withMessages(['barcode' => 'Ce code-barres est déjà attribué à un autre produit.']);
        }
    }
}
