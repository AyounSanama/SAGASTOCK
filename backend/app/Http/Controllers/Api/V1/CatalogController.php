<?php

namespace App\Http\Controllers\Api\V1;

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
use App\Services\ModuleActivationService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    private const TYPES = ['category', 'therapeutic_family', 'unit', 'dosage_form', 'dosage', 'administration_route', 'pathology', 'target_population', 'protocol', 'activity_type', 'care_level'];

    public function __construct(private AuditService $audit, private UserScopeService $scopes, private ModuleActivationService $modules) {}

    public function references(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);

        $query = $request->string('status')->toString() === 'archived'
            ? CatalogReference::onlyTrashed()->where('organization_id', $organization->id)
            : CatalogReference::where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $organization->id));

        return response()->json($query
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('reference_type', $type))
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))
            ->orderBy('reference_type')->orderBy('name')->paginate(50));
    }

    public function storeReference(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $data = $this->referenceData($request, $organization);
        $model = $organization->catalogReferences()->create($data);

        return $this->created($request, 'reference.created', $model, 'reference');
    }

    public function updateReference(Request $request, Organization $organization, CatalogReference $reference): JsonResponse
    {
        $this->owned($request, $organization, $reference);
        $old = $reference->toArray();
        $reference->update($this->referenceData($request, $organization, $reference));
        $this->audit->record($request, 'reference.updated', $reference, $old, $reference->fresh()->toArray());

        return response()->json(['reference' => $reference]);
    }

    public function archiveReference(Request $request, Organization $organization, CatalogReference $reference): JsonResponse
    {
        return $this->archive($request, $organization, $reference, 'reference.archived');
    }

    public function restoreReference(Request $request, Organization $organization, string $reference): JsonResponse
    {
        return $this->restore($request, $organization, CatalogReference::onlyTrashed()->findOrFail($reference), 'reference.restored', 'reference');
    }

    public function suppliers(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);

        return response()->json($organization->suppliers()->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))->orderBy('name')->paginate(30));
    }

    public function storeSupplier(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $model = $organization->suppliers()->create($this->supplierData($request, $organization));

        return $this->created($request, 'supplier.created', $model, 'supplier');
    }

    public function updateSupplier(Request $request, Organization $organization, Supplier $supplier): JsonResponse
    {
        $this->owned($request, $organization, $supplier);
        $supplier->update($this->supplierData($request, $organization, $supplier));
        $this->audit->record($request, 'supplier.updated', $supplier);

        return response()->json(['supplier' => $supplier]);
    }

    public function archiveSupplier(Request $request, Organization $organization, Supplier $supplier): JsonResponse
    {
        return $this->archive($request, $organization, $supplier, 'supplier.archived');
    }

    public function restoreSupplier(Request $request, Organization $organization, string $supplier): JsonResponse
    {
        return $this->restore($request, $organization, Supplier::onlyTrashed()->findOrFail($supplier), 'supplier.restored', 'supplier');
    }

    public function products(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);

        $query = $request->string('status')->toString() === 'archived'
            ? Product::onlyTrashed()->where('organization_id', $organization->id)
            : $organization->products();

        return response()->json($query->with(['category', 'therapeuticFamily', 'baseUnit', 'dosageForm', 'administrationRoute', 'codes'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where(fn ($n) => $n
                ->where('name', 'like', "%$s%")->orWhere('generic_name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")
                ->orWhereHas('codes', fn ($c) => $c->where('value', 'like', "%$s%"))))
            ->when($request->string('type')->toString(), fn ($q, $type) => $q->where('product_type', $type))
            ->when($request->string('status')->toString() === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->string('status')->toString() === 'active', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->paginate(30));
    }

    public function storeProduct(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $data = $this->productData($request, $organization);
        $codes = $data['codes'] ?? [];
        unset($data['codes']);
        $product = $organization->products()->create($data);
        $this->syncCodes($product, $codes);
        $this->audit->record($request, 'product.created', $product, [], $product->toArray());

        return response()->json(['product' => $product->load('codes')], 201);
    }

    public function updateProduct(Request $request, Organization $organization, Product $product): JsonResponse
    {
        $this->owned($request, $organization, $product);
        $data = $this->productData($request, $organization, $product);
        $codes = $data['codes'] ?? null;
        unset($data['codes']);
        $old = $product->toArray();
        $product->update($data);
        if ($codes !== null) {
            $product->codes()->delete();
            $this->syncCodes($product, $codes);
        }
        $this->audit->record($request, 'product.updated', $product, $old, $product->fresh()->toArray());

        return response()->json(['product' => $product->load('codes')]);
    }

    public function archiveProduct(Request $request, Organization $organization, Product $product): JsonResponse
    {
        return $this->archive($request, $organization, $product, 'product.archived');
    }

    public function restoreProduct(Request $request, Organization $organization, string $product): JsonResponse
    {
        return $this->restore($request, $organization, Product::onlyTrashed()->findOrFail($product), 'product.restored', 'product');
    }

    public function storeBatch(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $data = $this->batchData($request, $organization);
        $model = $organization->batches()->create($data);

        return $this->created($request, 'batch.created', $model, 'batch');
    }

    public function batches(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);

        $query = $request->string('status')->toString() === 'archived'
            ? Batch::onlyTrashed()->where('organization_id', $organization->id)
            : $organization->batches();

        return response()->json($query->with(['product:id,code,name', 'supplier:id,code,name'])
            ->when($request->string('search')->toString(), fn ($q, $s) => $q
                ->where(fn ($nested) => $nested->where('batch_number', 'like', "%$s%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%$s%"))))
            ->when(in_array($request->string('status')->toString(), ['available', 'quarantine', 'expired', 'destroyed'], true),
                fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->orderBy('expires_on')->paginate(30));
    }

    public function updateBatch(Request $request, Organization $organization, Batch $batch): JsonResponse
    {
        $this->owned($request, $organization, $batch);
        $old = $batch->toArray();
        $batch->update($this->batchData($request, $organization, $batch));
        $this->audit->record($request, 'batch.updated', $batch, $old, $batch->fresh()->toArray());

        return response()->json(['batch' => $batch->load(['product', 'supplier'])]);
    }

    public function archiveBatch(Request $request, Organization $organization, Batch $batch): JsonResponse
    {
        return $this->archive($request, $organization, $batch, 'batch.archived', false);
    }

    public function restoreBatch(Request $request, Organization $organization, string $batch): JsonResponse
    {
        return $this->restore($request, $organization, Batch::onlyTrashed()->findOrFail($batch), 'batch.restored', 'batch', false);
    }

    public function storeKit(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $data = $this->kitData($request, $organization);
        $items = $data['items'];
        unset($data['items']);
        $kit = $organization->kits()->create($data);
        $kit->products()->sync(collect($items)->mapWithKeys(fn ($i) => [$i['product_id'] => ['quantity' => $i['quantity']]]));

        return $this->created($request, 'kit.created', $kit->load('products'), 'kit');
    }

    public function updateKit(Request $request, Organization $organization, Kit $kit): JsonResponse
    {
        $this->owned($request, $organization, $kit);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('kits')->where('organization_id', $organization->id)->ignore($kit->id)],
            'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['sometimes', 'boolean'],
            'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'uuid', 'exists:products,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ]);
        $items = $data['items'];
        unset($data['items']);
        abort_unless($organization->products()->whereIn('id', collect($items)->pluck('product_id'))->count() === collect($items)->pluck('product_id')->unique()->count(), 422);
        $kit->update($data);
        $kit->products()->sync(collect($items)->mapWithKeys(fn ($i) => [$i['product_id'] => ['quantity' => $i['quantity']]]));
        $this->audit->record($request, 'kit.updated', $kit);

        return response()->json(['kit' => $kit->load('products')]);
    }

    public function archiveKit(Request $request, Organization $organization, Kit $kit): JsonResponse
    {
        return $this->archive($request, $organization, $kit, 'kit.archived');
    }

    public function restoreKit(Request $request, Organization $organization, string $kit): JsonResponse
    {
        return $this->restore($request, $organization, Kit::onlyTrashed()->findOrFail($kit), 'kit.restored', 'kit');
    }

    public function lists(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);

        $query = $request->string('status')->toString() === 'archived'
            ? StandardList::onlyTrashed()->where('organization_id', $organization->id)
            : $organization->standardLists();

        $projectIds = $this->scopes->projects($request->user())->pluck('projects.id');
        $query->where(function ($scope) use ($projectIds) {
            $scope->where(fn ($project) => $project->where('scope_type', 'project')->whereIn('scope_id', $projectIds));
        });

        return response()->json($query->with(['versions.products:id,code,name', 'latestVersion'])
            ->when($request->string('search')->toString(), fn ($q, $search) => $q->where(fn ($nested) => $nested
                ->where('name', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(30));
    }

    public function storeList(Request $request, Organization $organization): JsonResponse
    {
        $this->access($request, $organization);
        $data = $this->listData($request, $organization);
        $items = $data['items'] ?? [];
        unset($data['items']);
        $list = $organization->standardLists()->create($data);
        $version = $list->versions()->create(['version_number' => 1, 'status' => 'draft']);
        $this->syncListItems($version, $items, $organization);
        $this->audit->record($request, 'standard_list.created', $list, [], $list->toArray());

        return response()->json(['list' => $list->load('versions.products')], 201);
    }

    public function updateList(Request $request, Organization $organization, StandardList $standardList): JsonResponse
    {
        $this->owned($request, $organization, $standardList);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('standard_lists')->where('organization_id', $organization->id)->ignore($standardList->id)],
            'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'],
            'allow_outside_list' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'],
        ]);
        $standardList->update($data);
        $this->audit->record($request, 'standard_list.updated', $standardList);

        return response()->json(['list' => $standardList->load('versions.products')]);
    }

    public function archiveList(Request $request, Organization $organization, StandardList $standardList): JsonResponse
    {
        return $this->archive($request, $organization, $standardList, 'standard_list.archived');
    }

    public function restoreList(Request $request, Organization $organization, string $standardList): JsonResponse
    {
        return $this->restore($request, $organization, StandardList::onlyTrashed()->findOrFail($standardList), 'standard_list.restored', 'list');
    }

    public function newListVersion(Request $request, Organization $organization, StandardList $standardList): JsonResponse
    {
        $this->owned($request, $organization, $standardList);
        $data = $request->validate(['change_notes' => ['nullable', 'string', 'max:3000'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'uuid', 'exists:products,id'], 'items.*.minimum_quantity' => ['nullable', 'numeric', 'min:0'], 'items.*.maximum_quantity' => ['nullable', 'numeric', 'gte:items.*.minimum_quantity']]);
        $number = ((int) $standardList->versions()->max('version_number')) + 1;
        $version = $standardList->versions()->create(['version_number' => $number, 'status' => 'draft', 'change_notes' => $data['change_notes'] ?? null]);
        $this->syncListItems($version, $data['items'], $organization);
        $this->audit->record($request, 'standard_list.version_created', $version);

        return response()->json(['version' => $version->load('products')], 201);
    }

    public function publishListVersion(Request $request, Organization $organization, StandardList $standardList, StandardListVersion $version): JsonResponse
    {
        $this->owned($request, $organization, $standardList);
        abort_unless($version->standard_list_id === $standardList->id, 404);
        abort_unless($version->status === 'draft', 422, 'Seule une version brouillon peut être publiée.');
        abort_unless($version->products()->exists(), 422, 'La liste doit contenir au moins un produit.');
        $data = $request->validate(['effective_from' => ['nullable', 'date'], 'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from']]);
        $standardList->versions()->where('status', 'published')->update(['status' => 'superseded']);
        $version->update([...$data, 'status' => 'published', 'published_by' => $request->user()->id, 'published_at' => now()]);
        $this->audit->record($request, 'standard_list.published', $version, [], ['version_number' => $version->version_number]);

        return response()->json(['version' => $version->load('products')]);
    }

    private function referenceData(Request $r, Organization $o, ?CatalogReference $m = null): array
    {
        return $r->validate(['reference_type' => ['required', Rule::in(self::TYPES)], 'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('catalog_references')->where(fn ($q) => $q->where('organization_id', $o->id)->where('reference_type', $r->input('reference_type')))->ignore($m?->id)], 'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:2000'], 'metadata' => ['nullable', 'array'], 'is_active' => ['sometimes', 'boolean']]);
    }

    private function supplierData(Request $r, Organization $o, ?Supplier $m = null): array
    {
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('suppliers')->where('organization_id', $o->id)->ignore($m?->id)], 'name' => ['required', 'string', 'max:190'], 'supplier_type' => ['required', Rule::in(['supplier', 'partner', 'manufacturer', 'donor'])], 'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:1000'], 'country_code' => ['nullable', 'string', 'size:2'], 'is_active' => ['sometimes', 'boolean']]);
        if (isset($d['country_code'])) {
            $d['country_code'] = strtoupper($d['country_code']);
        }

return $d;
    }

    private function productData(Request $r, Organization $o, ?Product $m = null): array
    {
        $d = $r->validate(['category_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'therapeutic_family_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'base_unit_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'dosage_form_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'administration_route_id' => ['nullable', 'uuid', 'exists:catalog_references,id'], 'code' => ['required', 'alpha_dash', 'max:60', Rule::unique('products')->where('organization_id', $o->id)->ignore($m?->id)], 'name' => ['required', 'string', 'max:190'], 'generic_name' => ['nullable', 'string', 'max:190'], 'product_type' => ['required', Rule::in(['medicine', 'consumable', 'device', 'reagent', 'program_input', 'other'])], 'strength' => ['nullable', 'string', 'max:100'], 'description' => ['nullable', 'string', 'max:3000'], 'is_controlled' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'], 'codes' => ['nullable', 'array'], 'codes.*.code_type' => ['required', Rule::in(['internal', 'barcode', 'qr', 'gs1'])], 'codes.*.value' => ['required', 'string', 'max:190', 'distinct'], 'codes.*.is_primary' => ['sometimes', 'boolean']]);
        foreach (['category_id' => 'category', 'therapeutic_family_id' => 'therapeutic_family', 'base_unit_id' => 'unit', 'dosage_form_id' => 'dosage_form', 'administration_route_id' => 'administration_route'] as $field => $type) {
            if (! empty($d[$field])) {
                abort_unless(CatalogReference::whereKey($d[$field])->where('reference_type', $type)->where(fn ($q) => $q->whereNull('organization_id')->orWhere('organization_id', $o->id))->exists(), 422, "Référence $field invalide.");
            }
        }

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

    private function kitData(Request $r, Organization $o): array
    {
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('kits')->where('organization_id', $o->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'is_active' => ['sometimes', 'boolean'], 'items' => ['required', 'array', 'min:1'], 'items.*.product_id' => ['required', 'uuid', 'exists:products,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0']]);
        abort_unless($o->products()->whereIn('id', collect($d['items'])->pluck('product_id'))->count() === collect($d['items'])->pluck('product_id')->unique()->count(), 422);

        return $d;
    }

    private function listData(Request $r, Organization $o): array
    {
        $d = $r->validate(['code' => ['required', 'alpha_dash', 'max:60', Rule::unique('standard_lists')->where('organization_id', $o->id)], 'name' => ['required', 'string', 'max:190'], 'description' => ['nullable', 'string', 'max:3000'], 'scope_type' => ['required', Rule::in(['organization', 'project', 'program', 'donor', 'facility', 'care_level', 'activity', 'pathology', 'population'])], 'scope_id' => ['required', 'uuid'], 'allow_outside_list' => ['sometimes', 'boolean'], 'is_active' => ['sometimes', 'boolean'], 'items' => ['nullable', 'array'], 'items.*.product_id' => ['required', 'uuid', 'exists:products,id'], 'items.*.minimum_quantity' => ['nullable', 'numeric', 'min:0'], 'items.*.maximum_quantity' => ['nullable', 'numeric', 'min:0'], 'items.*.notes' => ['nullable', 'string', 'max:1000']]);
        $this->listScope($o, $d['scope_type'], $d['scope_id']);

        return $d;
    }

    private function listScope(Organization $o, string $type, string $id): void
    {
        $ok = match ($type) {
            'organization' => $o->id === $id,'project' => $o->projects()->whereKey($id)->exists(),'program' => $o->programs()->whereKey($id)->exists(),'donor' => $o->donors()->whereKey($id)->exists(),'facility' => $o->healthFacilities()->whereKey($id)->exists(),'care_level' => CatalogReference::whereKey($id)->where('reference_type', 'care_level')->exists(),'activity' => CatalogReference::whereKey($id)->where('reference_type', 'activity_type')->exists(),'pathology' => CatalogReference::whereKey($id)->where('reference_type', 'pathology')->exists(),'population' => CatalogReference::whereKey($id)->where('reference_type', 'target_population')->exists()
        };
        abort_unless($ok, 422, 'Périmètre de liste invalide.');
    }

    private function syncCodes(Product $p, array $codes): void
    {
        foreach ($codes as $code) {
            $p->codes()->create($code);
        }
    }

    private function syncListItems(StandardListVersion $v, array $items, Organization $o): void
    {
        $ids = collect($items)->pluck('product_id')->unique();
        abort_unless($o->products()->whereIn('id', $ids)->count() === $ids->count(), 422);
        $v->products()->sync(collect($items)->mapWithKeys(fn ($i) => [$i['product_id'] => ['minimum_quantity' => $i['minimum_quantity'] ?? null, 'maximum_quantity' => $i['maximum_quantity'] ?? null, 'notes' => $i['notes'] ?? null]]));
    }

    private function access(Request $r, Organization $o): void
    {
        abort_unless($this->scopes->organizations($r->user())->whereKey($o->id)->exists(), 404);
        abort_unless($this->modules->isEnabled('references', $o->id), 403, 'Le module Référentiels est désactivé pour cette organisation.');
    }

    private function owned(Request $r, Organization $o, Model $m): void
    {
        $this->access($r, $o);
        abort_unless($m->organization_id === $o->id, 404);
    }

    private function created(Request $r,string $event,Model $m,string $key): JsonResponse
    {
        $this->audit->record($r,$event,$m,[],$m->toArray());

        return response()->json([$key => $m],201);
    }

    private function archive(Request $r,Organization $o,Model $m,string $event,bool $deactivate = true): JsonResponse
    {
        $this->owned($r,$o,$m);
        if ($deactivate && in_array('is_active',$m->getFillable())) {
            $m->update(['is_active' => false]);
        }$m->delete();
        $this->audit->record($r,$event,$m);

        return response()->json(status: 204);
    }

    private function restore(Request $r,Organization $o,Model $m,string $event,string $key,bool $activate = true): JsonResponse
    {
        $this->owned($r,$o,$m);
        $m->restore();
        if ($activate && in_array('is_active',$m->getFillable())) {
            $m->update(['is_active' => true]);
        }$this->audit->record($r,$event,$m);

        return response()->json([$key => $m]);
    }
}
