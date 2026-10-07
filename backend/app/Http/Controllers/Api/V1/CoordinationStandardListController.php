<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CoordinationStandardListController as WebController;
use App\Models\HealthFacility;
use App\Models\Product;
use App\Models\Project;
use App\Services\CoordinationStandardListService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Niveau 6 — « Liste Standard » de la Coordination sur mobile : mêmes règles
 * que le Web (actions partagées avec le contrôleur Web).
 */
class CoordinationStandardListController extends Controller
{
    public function __construct(
        private CoordinationStandardListService $lists,
        private WebController $web,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $projects = $this->lists->projects($request->user());
        $project = $request->filled('project')
            ? $projects->firstWhere('id', $request->string('project')->toString())
            : $projects->first();
        abort_if($request->filled('project') && ! $project, 404);
        $facilities = $project ? $this->lists->facilities($project) : collect();
        $facility = $request->filled('facility') ? $facilities->firstWhere('id', $request->string('facility')->toString()) : null;
        abort_if($request->filled('facility') && ! $facility, 404);
        $list = $project ? $this->lists->list($project, $facility) : null;
        $barcodes = $list ? $this->lists->barcodes($list['rows']->pluck('product.id')) : collect();

        return response()->json([
            'projects' => $projects->map(fn (Project $item) => $item->only(['id', 'code', 'name', 'status']))->values(),
            'project' => $project?->only(['id', 'code', 'name', 'status']),
            'title' => $list['title'] ?? null,
            'facilities' => $facilities->map(fn (HealthFacility $item) => $item->only(['id', 'code', 'name']))->values(),
            'facility' => $facility?->only(['id', 'code', 'name']),
            'can_manage' => $project && Gate::allows('configure', $project),
            'pathologies' => $list['pathologies'] ?? [],
            'products' => collect($list['rows'] ?? [])->map(fn (array $row) => [
                'id' => $row['product']->id,
                'code' => $row['product']->code,
                'name' => trim($row['product']->name.' '.$row['product']->strength),
                'packaging' => $row['product']->packaging ?: collect([$row['product']->dosageForm?->name, $row['product']->baseUnit?->name])->filter()->join(', ') ?: null,
                'pathology' => $row['pathology'],
                'retained' => $row['retained'],
                'barcode' => $barcodes->get($row['product']->id),
            ])->values(),
        ]);
    }

    public function updateFacility(Request $request, Project $project, HealthFacility $facility): JsonResponse
    {
        $result = $this->web->saveFacility($request, $project, $facility);

        return response()->json(['excluded' => count($result['excluded']), 'restored' => count($result['restored'])]);
    }

    public function storeProduct(Request $request, Project $project): JsonResponse
    {
        $product = $this->web->createProduct($request, $project, '');

        return response()->json(['product' => $product->only(['id', 'code', 'name', 'packaging'])], 201);
    }

    public function updateBarcode(Request $request, Project $project, Product $product): JsonResponse
    {
        $this->web->saveBarcode($request, $project, $product);

        return response()->json(['barcode' => $product->codes()->where('code_type', 'barcode')->value('value')]);
    }
}
