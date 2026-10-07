<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\CatalogReference;
use App\Models\Kit;
use App\Models\Organization;
use App\Models\Product;
use App\Models\StandardList;
use App\Models\StandardListVersion;
use App\Models\Supplier;
use App\Services\AuditService;
use App\Services\CareLevelHierarchyService;
use App\Services\ModuleActivationService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogController extends Controller
{
    private const TYPES = ['category' => 'Catégorie', 'therapeutic_family' => 'Famille thérapeutique', 'unit' => 'Unité', 'dosage_form' => 'Forme pharmaceutique', 'dosage' => 'Dosage', 'administration_route' => "Voie d'administration", 'pathology' => 'Pathologie', 'target_population' => 'Population cible', 'protocol' => 'Protocole', 'activity_type' => "Type d'activité", 'care_level' => 'Niveau de soins'];

    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private ModuleActivationService $modules,
    ) {}

    public function home(Request $request, string $section): RedirectResponse
    {
        // Niveau 5 : Liste Standard de l'Admin Projet, en consultation (maquette AdminProjet 04).
        if (app(\App\Services\GovernanceService::class)->roleCode($request->user()) === \App\Services\GovernanceService::PROJECT_ADMIN) {
            return redirect()->route('project-admin.standard-list');
        }
        // Niveau 6 : Liste Standard de la Coordination (par projet et par FOSA).
        if ($section === 'lists' && app(\App\Services\GovernanceService::class)->roleCode($request->user()) === \App\Services\GovernanceService::COORDINATION_ADMIN) {
            return redirect()->route('coordination.standard-list.show');
        }
        $organization = $this->scopes->organizations($request->user())
            ->where('is_active', true)
            ->orderBy('name')
            ->first();
        abort_unless($organization, 404, 'Aucune organisation accessible pour ce compte.');

        return redirect()->route('organizations.catalog.index', [
            $organization,
            'section' => $section,
        ]);
    }

    public function index(Request $request, Organization $organization): View
    {
        $this->allow($request, 'catalog.view');
        $this->access($request, $organization);
        $references = CatalogReference::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organization->id))->orderBy('reference_type')->orderBy('name')->get()->groupBy('reference_type');
        $products = $organization->products()->with(['category', 'baseUnit', 'codes'])->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")->orWhere('generic_name', 'like', "%$s%")))->orderBy('name')->paginate(20)->withQueryString();

        return view('catalog.index', [
            'organization' => $organization, 'referenceTypes' => self::TYPES, 'references' => $references, 'products' => $products,
            'productOptions' => $organization->products()->orderBy('name')->get(['id', 'name', 'code']),
            'productStats' => [
                'total' => $organization->products()->count(),
                'medicines' => $organization->products()->where('product_type', 'medicine')->count(),
                'controlled' => $organization->products()->where('is_controlled', true)->count(),
                'archived' => $organization->products()->onlyTrashed()->count(),
            ],
            'suppliers' => $organization->suppliers()->orderBy('name')->get(),
            'batches' => $organization->batches()->with(['product', 'supplier'])->orderBy('expires_on')->get(),
            'kits' => $organization->kits()->with('products')->orderBy('name')->get(),
            'lists' => $organization->standardLists()->where('scope_type', 'project')->whereIn('scope_id', $this->scopes->projects($request->user())->pluck('projects.id'))->with(['versions.products', 'latestVersion'])->orderBy('name')->get(),
            'archivedReferences' => $organization->catalogReferences()->onlyTrashed()->get(),
            'archivedProducts' => $organization->products()->onlyTrashed()->get(),
            'archivedSuppliers' => $organization->suppliers()->onlyTrashed()->get(),
            'archivedBatches' => $organization->batches()->onlyTrashed()->with('product')->get(),
            'archivedKits' => $organization->kits()->onlyTrashed()->get(),
            'archivedLists' => $organization->standardLists()->onlyTrashed()->where('scope_type', 'project')->whereIn('scope_id', $this->scopes->projects($request->user())->pluck('projects.id'))->get(),
            'projects' => $organization->projects()->orderBy('name')->get(), 'programs' => $organization->programs()->orderBy('name')->get(),
            'donors' => $organization->donors()->orderBy('name')->get(), 'facilities' => $organization->healthFacilities()->orderBy('name')->get(),
        ]);
    }

    public function createProduct(Request $request, Organization $o): View
    {
        $this->manage($request, $o);
        $references = CatalogReference::where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $o->id))
            ->where('is_active', true)->orderBy('name')->get()->groupBy('reference_type');

        return view('catalog.create-product', [
            'organization' => $o,
            'references' => $references,
        ]);
    }

    public function storeReference(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $m = $o->catalogReferences()->create($this->referenceData($r, $o));

        return $this->saved($r, $m, 'reference.created', 'Référence créée.');
    }

    public function updateReference(Request $r, Organization $o, CatalogReference $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $m->update($this->referenceData($r, $o, $m));

        return $this->saved($r, $m, 'reference.updated', 'Référence mise à jour.');
    }

    public function archiveReference(Request $r, Organization $o, CatalogReference $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        app(CareLevelHierarchyService::class)->guardArchive($m);

        return $this->archive($r, $o, $m, 'reference.archived', 'Référence archivée.');
    }

    public function restoreReference(Request $r, Organization $o, string $id): RedirectResponse
    {
        return $this->restore($r, $o, CatalogReference::onlyTrashed()->findOrFail($id), 'reference.restored', 'Référence restaurée.');
    }

    public function storeSupplier(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $m = $o->suppliers()->create($this->supplierData($r, $o));

        return $this->saved($r, $m, 'supplier.created', 'Fournisseur ou partenaire créé.');
    }

    public function updateSupplier(Request $r, Organization $o, Supplier $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $m->update($this->supplierData($r, $o, $m));

        return $this->saved($r, $m, 'supplier.updated', 'Fournisseur mis à jour.');
    }

    public function archiveSupplier(Request $r, Organization $o, Supplier $m): RedirectResponse
    {
        return $this->archive($r, $o, $m, 'supplier.archived', 'Fournisseur archivé.');
    }

    public function restoreSupplier(Request $r, Organization $o, string $id): RedirectResponse
    {
        return $this->restore($r, $o, Supplier::onlyTrashed()->findOrFail($id), 'supplier.restored', 'Fournisseur restauré.');
    }

    public function storeProduct(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $data = $this->productData($r, $o);
        $barcode = $data['barcode'] ?? null;
        unset($data['barcode']);
        $m = $o->products()->create($data);
        if ($barcode) {
            $m->codes()->create(['code_type' => 'barcode', 'value' => $barcode, 'is_primary' => true]);
        }

        $this->audit->record($r, 'product.created', $m, [], $m->toArray());

        return redirect()->route('organizations.catalog.index', [$o, 'search' => $m->code])
            ->with('status', 'Médicament ou produit médical ajouté avec succès.');
    }

    public function updateProduct(Request $r, Organization $o, Product $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $data = $this->productData($r, $o, $m);
        $barcode = $data['barcode'] ?? null;
        unset($data['barcode']);
        $m->update($data);
        if ($barcode) {
            $m->codes()->updateOrCreate(['code_type' => 'barcode'], ['value' => $barcode, 'is_primary' => true]);
        }

        return $this->saved($r, $m, 'product.updated', 'Produit mis à jour.');
    }

    public function archiveProduct(Request $r, Organization $o, Product $m): RedirectResponse
    {
        return $this->archive($r, $o, $m, 'product.archived', 'Produit archivé.');
    }

    public function restoreProduct(Request $r, Organization $o, string $id): RedirectResponse
    {
        return $this->restore($r, $o, Product::onlyTrashed()->findOrFail($id), 'product.restored', 'Produit restauré.');
    }

    public function storeBatch(Request $r, Organization $o): RedirectResponse
    {
        $this->allow($r, 'batches.manage');
        $this->access($r, $o);
        $m = $o->batches()->create($this->batchData($r, $o));

        return $this->saved($r, $m, 'batch.created', 'Lot créé.');
    }

    public function updateBatch(Request $r, Organization $o, Batch $m): RedirectResponse
    {
        $this->allow($r, 'batches.manage');
        $this->owned($r, $o, $m);
        $m->update($this->batchData($r, $o, $m));

        return $this->saved($r, $m, 'batch.updated', 'Lot mis à jour.');
    }

    public function archiveBatch(Request $r, Organization $o, Batch $m): RedirectResponse
    {
        $this->allow($r, 'batches.manage');

        return $this->archive($r, $o, $m, 'batch.archived', 'Lot archivé.', false);
    }

    public function restoreBatch(Request $r, Organization $o, string $id): RedirectResponse
    {
        $this->allow($r, 'batches.manage');

        return $this->restore($r, $o, Batch::onlyTrashed()->findOrFail($id), 'batch.restored', 'Lot restauré.', false);
    }

    public function storeKit(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('kits')->where('organization_id', $o->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'product_ids' => ['required', 'array', 'min:1'], 'product_ids.*' => ['uuid', 'exists:products,id'], 'quantities' => ['required', 'array'], 'quantities.*' => ['numeric', 'gt:0']]);
        abort_unless($o->products()->whereIn('id', $d['product_ids'])->count() === count(array_unique($d['product_ids'])), 422);
        $kit = $o->kits()->create(['code' => $d['code'], 'name' => $d['name'], 'description' => $d['description'] ?? null, 'is_active' => true]);
        $kit->products()->sync(collect($d['product_ids'])->mapWithKeys(fn ($id, $i) => [$id => ['quantity' => $d['quantities'][$i] ?? 1]]));

        return $this->saved($r, $kit, 'kit.created', 'Kit créé.');
    }

    public function updateKit(Request $r, Organization $o, Kit $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('kits')->where('organization_id', $o->id)->ignore($m->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['nullable', 'boolean'], 'product_ids' => ['required', 'array', 'min:1'], 'product_ids.*' => ['uuid', 'exists:products,id'], 'quantities' => ['required', 'array'], 'quantities.*' => ['numeric', 'gt:0']]);
        abort_unless($o->products()->whereIn('id', $d['product_ids'])->count() === count(array_unique($d['product_ids'])), 422);
        $m->update(['code' => $d['code'], 'name' => $d['name'], 'description' => $d['description'] ?? null, 'is_active' => $r->boolean('is_active')]);
        $m->products()->sync(collect($d['product_ids'])->mapWithKeys(fn ($id, $i) => [$id => ['quantity' => $d['quantities'][$i] ?? 1]]));

        return $this->saved($r, $m, 'kit.updated', 'Kit mis à jour.');
    }

    public function archiveKit(Request $r, Organization $o, Kit $m): RedirectResponse
    {
        return $this->archive($r, $o, $m, 'kit.archived', 'Kit archivé.');
    }

    public function restoreKit(Request $r, Organization $o, string $id): RedirectResponse
    {
        return $this->restore($r, $o, Kit::onlyTrashed()->findOrFail($id), 'kit.restored', 'Kit restauré.');
    }

    public function storeList(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $d = $this->listData($r, $o);
        $productIds = $d['product_ids'];
        unset($d['product_ids']);
        $list = $o->standardLists()->create($d);
        $version = $list->versions()->create(['version_number' => 1, 'status' => 'draft']);
        $version->products()->sync($productIds);

        return $this->saved($r, $list, 'standard_list.created', 'Liste standard créée en brouillon.');
    }

    public function updateList(Request $r, Organization $o, StandardList $m): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('standard_lists')->where('organization_id', $o->id)->ignore($m->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'allow_outside_list' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean']]);
        $d['allow_outside_list'] = $r->boolean('allow_outside_list');
        $d['is_active'] = $r->boolean('is_active');
        $m->update($d);

        return $this->saved($r, $m, 'standard_list.updated', 'Liste mise à jour.');
    }

    public function archiveList(Request $r, Organization $o, StandardList $m): RedirectResponse
    {
        return $this->archive($r, $o, $m, 'standard_list.archived', 'Liste archivée.');
    }

    public function restoreList(Request $r, Organization $o, string $id): RedirectResponse
    {
        return $this->restore($r, $o, StandardList::onlyTrashed()->findOrFail($id), 'standard_list.restored', 'Liste restaurée.');
    }

    public function newVersion(Request $r, Organization $o, StandardList $list): RedirectResponse
    {
        $this->manageOwned($r, $o, $list);
        $d = $r->validate(['product_ids' => ['required', 'array', 'min:1'], 'product_ids.*' => ['uuid', 'exists:products,id'], 'change_notes' => ['nullable', 'string', 'max:3000']]);
        abort_unless($o->products()->whereIn('id', $d['product_ids'])->count() === count(array_unique($d['product_ids'])), 422);
        $version = $list->versions()->create(['version_number' => ((int) $list->versions()->max('version_number')) + 1, 'status' => 'draft', 'change_notes' => $d['change_notes'] ?? null]);
        $version->products()->sync($d['product_ids']);

        return $this->saved($r, $version, 'standard_list.version_created', 'Nouvelle version créée.');
    }

    public function publish(Request $r, Organization $o, StandardList $list, StandardListVersion $version): RedirectResponse
    {
        $this->allow($r, 'catalog.publish');
        $this->owned($r, $o, $list);
        abort_unless($version->standard_list_id === $list->id && $version->status === 'draft' && $version->products()->exists(), 422);
        $d = $r->validate(['effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $list->versions()->where('status', 'published')->update(['status' => 'superseded']);
        $version->update([...$d, 'status' => 'published', 'published_by' => $r->user()->id, 'published_at' => now()]);

        return $this->saved($r, $version, 'standard_list.published', 'Liste standard publiée.');
    }

    public function exportProducts(Request $r, Organization $o): StreamedResponse
    {
        $this->allow($r, 'catalog.view');
        $this->access($r, $o);

        return response()->streamDownload(function () use ($o) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['code', 'name', 'generic_name', 'product_type', 'strength', 'barcode'], ';');
            $o->products()->with('codes')->orderBy('name')->chunk(200, function ($products) use ($out) {
                foreach ($products as $product) {
                    fputcsv($out, [
                        $product->code, $product->name, $product->generic_name,
                        $product->product_type, $product->strength,
                        $product->codes->firstWhere('code_type', 'barcode')?->value,
                    ], ';');
                }
            });
            fclose($out);
        }, 'produits-'.$o->code.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importProducts(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $r->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);
        $handle = fopen($r->file('file')->getRealPath(), 'r');
        $header = fgetcsv($handle, 0, ';');
        $header = array_map(fn ($value) => trim(str_replace("\xEF\xBB\xBF", '', $value)), $header ?: []);
        abort_unless(empty(array_diff(['code', 'name', 'product_type'], $header)), 422, 'Colonnes requises : code, name, product_type.');
        $count = 0;
        DB::transaction(function () use ($handle, $header, $o, &$count) {
            while (($row = fgetcsv($handle, 0, ';')) !== false) {
                if (count($row) !== count($header)) {
                    continue;
                }
                $data = array_combine($header, $row);
                if (empty($data['code']) || empty($data['name'])) {
                    continue;
                }
                $product = $o->products()->updateOrCreate(['code' => $data['code']], [
                    'name' => $data['name'],
                    'generic_name' => ($data['generic_name'] ?? '') ?: null,
                    'product_type' => $data['product_type'],
                    'strength' => ($data['strength'] ?? '') ?: null,
                    'is_active' => true,
                ]);
                if (! empty($data['barcode'] ?? null)) {
                    $product->codes()->updateOrCreate(['code_type' => 'barcode'], ['value' => $data['barcode'], 'is_primary' => true]);
                }
                $count++;
            }
        });
        fclose($handle);
        $this->audit->record($r, 'products.imported', $o, [], ['count' => $count]);

        return back()->with('status', "{$count} produit(s) importé(s).");
    }

    public function exportProductsExcel(Request $r, Organization $o): BinaryFileResponse
    {
        $this->allow($r, 'catalog.view');
        $this->access($r, $o);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Produits');
        $sheet->fromArray(['code', 'name', 'generic_name', 'product_type', 'strength', 'barcode'], null, 'A1');
        $row = 2;
        $o->products()->with('codes')->orderBy('name')->chunk(200, function ($products) use ($sheet, &$row) {
            foreach ($products as $product) {
                $sheet->fromArray([
                    $product->code, $product->name, $product->generic_name,
                    $product->product_type, $product->strength,
                    $product->codes->firstWhere('code_type', 'barcode')?->value,
                ], null, "A{$row}");
                $row++;
            }
        });
        $sheet->freezePane('A2');
        $sheet->setAutoFilter($sheet->calculateWorksheetDimension());
        foreach (range('A', 'F') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $path = tempnam(storage_path('app'), 'catalog-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'produits-'.$o->code.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function importProductsExcel(Request $r, Organization $o): RedirectResponse
    {
        $this->manage($r, $o);
        $r->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']]);
        $rows = IOFactory::load($r->file('file')->getRealPath())->getActiveSheet()->toArray();
        $header = array_map(fn ($value) => trim((string) $value), array_shift($rows) ?: []);
        abort_unless(empty(array_diff(['code', 'name', 'product_type'], $header)), 422, 'Colonnes requises : code, name, product_type.');
        $count = 0;
        DB::transaction(function () use ($rows, $header, $o, &$count) {
            foreach ($rows as $row) {
                $row = array_slice(array_pad($row, count($header), null), 0, count($header));
                $data = array_combine($header, $row);
                if (blank($data['code'] ?? null) || blank($data['name'] ?? null) || blank($data['product_type'] ?? null)) {
                    continue;
                }
                $product = $o->products()->updateOrCreate(['code' => trim((string) $data['code'])], [
                    'name' => trim((string) $data['name']),
                    'generic_name' => blank($data['generic_name'] ?? null) ? null : trim((string) $data['generic_name']),
                    'product_type' => trim((string) $data['product_type']),
                    'strength' => blank($data['strength'] ?? null) ? null : trim((string) $data['strength']),
                    'is_active' => true,
                ]);
                if (! blank($data['barcode'] ?? null)) {
                    $product->codes()->updateOrCreate(['code_type' => 'barcode'], ['value' => trim((string) $data['barcode']), 'is_primary' => true]);
                }
                $count++;
            }
        });
        $this->audit->record($r, 'products.imported', $o, [], ['count' => $count, 'format' => 'xlsx']);

        return back()->with('status', "{$count} produit(s) importé(s) depuis Excel.");
    }

    private function referenceData(Request $r, Organization $o, ?CatalogReference $m = null): array
    {
        $d = $r->validate(['reference_type' => ['required', Rule::in(array_keys(self::TYPES))], 'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('catalog_references')->where(fn ($q) => $q->where('organization_id', $o->id)->where('reference_type', $r->input('reference_type')))->ignore($m?->id)], 'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'], 'is_active' => ['nullable', 'boolean'], 'parent_id' => ['nullable', 'uuid']]);
        $d['is_active'] = $r->boolean('is_active', true);
        unset($d['parent_id']);

        return app(CareLevelHierarchyService::class)->apply($o, $d, $r->input('parent_id'), $m);
    }

    private function supplierData(Request $r, Organization $o, ?Supplier $m = null): array
    {
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('suppliers')->where('organization_id', $o->id)->ignore($m?->id)], 'name' => ['required', 'string', 'max:190'], 'supplier_type' => ['required', Rule::in(['supplier', 'partner', 'manufacturer', 'donor'])], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'], 'country_code' => ['nullable', 'string', 'size:2'], 'is_active' => ['nullable', 'boolean']]);
        $d['is_active'] = $r->boolean('is_active', true);
        if (isset($d['country_code'])) {
            $d['country_code'] = strtoupper($d['country_code']);
        }

        return $d;
    }

    private function productData(Request $r, Organization $o, ?Product $m = null): array
    {
        $d = $r->validate(['category_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'therapeutic_family_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'base_unit_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'dosage_form_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'administration_route_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('products')->where('organization_id', $o->id)->ignore($m?->id)], 'name' => ['required', 'string', 'max:190'], 'generic_name' => ['nullable', 'string', 'max:190'], 'product_type' => ['required', Rule::in(['medicine', 'consumable', 'device', 'reagent', 'program_input', 'other'])], 'strength' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:3000'], 'barcode' => ['nullable', 'string', 'max:190', Rule::unique('product_codes', 'value')->ignore($m?->codes()->where('code_type', 'barcode')->value('id'))], 'is_controlled' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean']]);
        $d['is_controlled'] = $r->boolean('is_controlled');
        $d['is_active'] = $r->boolean('is_active', true);

        return $d;
    }

    private function batchData(Request $r, Organization $o, ?Batch $m = null): array
    {
        $d = $r->validate(['product_id' => ['required', 'uuid', 'exists:products,id'], 'supplier_id' => ['nullable', 'uuid', 'exists:suppliers,id'], 'batch_number' => ['required', 'string', 'max:100', Rule::unique('batches')->where(fn ($q) => $q->where('organization_id', $o->id)->where('product_id', $r->input('product_id')))->ignore($m?->id)], 'manufactured_on' => ['nullable', 'date'], 'expires_on' => ['required', 'date', 'after:manufactured_on'], 'unit_cost' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'origin' => ['nullable', 'string', 'max:190'], 'status' => ['required', Rule::in(['available', 'quarantine', 'expired', 'destroyed'])]]);
        abort_unless($o->products()->whereKey($d['product_id'])->exists(), 422);
        if (! empty($d['supplier_id'])) {
            abort_unless($o->suppliers()->whereKey($d['supplier_id'])->exists(), 422);
        }if (isset($d['currency'])) {
            $d['currency'] = strtoupper($d['currency']);
        }

        return $d;
    }

    private function listData(Request $r, Organization $o): array
    {
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('standard_lists')->where('organization_id', $o->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'scope' => ['required', 'string'], 'allow_outside_list' => ['nullable', 'boolean'], 'product_ids' => ['required', 'array', 'min:1'], 'product_ids.*' => ['uuid', 'exists:products,id']]);
        [$type,$id] = array_pad(explode(':', $d['scope'], 2), 2, null);
        abort_unless($id, 422);
        $valid = match ($type) {
            'organization' => $id === $o->id,'project' => $o->projects()->whereKey($id)->exists(),'program' => $o->programs()->whereKey($id)->exists(),'donor' => $o->donors()->whereKey($id)->exists(),'facility' => $o->healthFacilities()->whereKey($id)->exists(),default => false
        };
        abort_unless($valid, 422, 'Périmètre invalide.');
        abort_unless($o->products()->whereIn('id', $d['product_ids'])->count() === count(array_unique($d['product_ids'])), 422);
        unset($d['scope']);
        $d['scope_type'] = $type;
        $d['scope_id'] = $id;
        $d['allow_outside_list'] = $r->boolean('allow_outside_list');
        $d['is_active'] = true;

        return $d;
    }

    private function allow(Request $r, string $p): void
    {
        abort_unless($r->user()?->hasPermission($p), 403);
    }

    private function access(Request $r, Organization $o): void
    {
        abort_unless($this->scopes->organizations($r->user())->whereKey($o->id)->exists(), 404);
        abort_unless($this->modules->isEnabled('references', $o->id), 403, 'Le module Référentiels est désactivé pour cette organisation.');
    }

    private function manage(Request $r, Organization $o): void
    {
        $this->allow($r, 'catalog.manage');
        $this->access($r, $o);
    }

    private function owned(Request $r, Organization $o, Model $m): void
    {
        $this->access($r, $o);
        abort_unless($m->organization_id === $o->id, 404);
    }

    private function manageOwned(Request $r, Organization $o, Model $m): void
    {
        $this->manage($r, $o);
        $this->owned($r, $o, $m);
    }

    private function saved(Request $r, Model $m, string $event, string $message): RedirectResponse
    {
        $this->audit->record($r, $event, $m, [], $m->toArray());

        return back()->with('status', $message);
    }

    private function archive(Request $r, Organization $o, Model $m, string $event, string $message, bool $deactivate = true): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        if ($deactivate && in_array('is_active', $m->getFillable())) {
            $m->update(['is_active' => false]);
        }$m->delete();

        return $this->saved($r, $m, $event, $message);
    }

    private function restore(Request $r, Organization $o, Model $m, string $event, string $message, bool $activate = true): RedirectResponse
    {
        $this->manageOwned($r, $o, $m);
        $m->restore();
        if ($activate && in_array('is_active', $m->getFillable())) {
            $m->update(['is_active' => true]);
        }

        return $this->saved($r,$m,$event,$message);
    }
}
