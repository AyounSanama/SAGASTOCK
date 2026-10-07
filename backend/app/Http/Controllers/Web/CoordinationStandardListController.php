<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HealthFacility;
use App\Models\Product;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\CoordinationStandardListService;
use App\Services\ProjectWizardService;
use App\Services\StandardListCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Niveau 6 — « Liste Standard » de la Coordination : liste de chaque projet,
 * décochage d'articles par FOSA, lien code-barres ↔ produit, ajout de produit
 * et import Excel. Seule la Coordination modifie la liste (cahier des charges).
 */
class CoordinationStandardListController extends Controller
{
    public function __construct(
        private CoordinationStandardListService $lists,
        private StandardListCatalogService $catalog,
        private ProjectWizardService $wizard,
        private AuditService $audit,
    ) {}

    public function show(Request $request): View
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
        $options = $project ? $this->wizard->standardListOptions($project) : null;

        return view('standard-lists.coordination', [
            'projects' => $projects,
            'project' => $project,
            'facilities' => $facilities,
            'facility' => $facility,
            'list' => $list,
            'barcodes' => $list ? $this->lists->barcodes($list['rows']->pluck('product.id')) : collect(),
            'careLevels' => collect($options['care_levels'] ?? []),
            'activities' => collect($options['pathologies'] ?? []),
            'populations' => collect($options['target_populations'] ?? []),
            'canManage' => $project && Gate::allows('configure', $project),
        ]);
    }

    /** Décochage par FOSA : articles retenus pour la FOSA, les autres sont retirés. */
    public function updateFacility(Request $request, Project $project, HealthFacility $facility): RedirectResponse
    {
        $result = $this->saveFacility($request, $project, $facility);

        return back()->with('status', 'Liste Standard de '.$facility->name.' enregistrée : '
            .count($result['excluded']).' article(s) décoché(s), '.count($result['restored']).' réintégré(s).');
    }

    /** « + Ajouter un produit » : produit de l'organisation, ajouté à la liste du projet. */
    public function storeProduct(Request $request, Project $project): RedirectResponse
    {
        $product = $this->createProduct($request, $project, '');

        return back()->with('status', 'Produit '.$product->code.' ajouté à la Liste Standard du projet '.$project->code.'.');
    }

    public function import(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('configure', $project);
        $request->validate(['file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240']], [], ['file' => 'fichier Excel']);
        $result = $this->catalog->import($project->organization, $request->file('file')->getRealPath());
        $this->catalog->appendToProjectList($project, $result['product_ids'], $request->user()->id);
        $this->audit->record($request, 'standard_list.imported', $project, [], collect($result)->except('product_ids')->all());

        return back()->with('status', StandardListCatalogService::importMessage($result))->with('import_errors', $result['errors']);
    }

    /** Lien code-barres ↔ produit. */
    public function updateBarcode(Request $request, Project $project, Product $product): RedirectResponse
    {
        $this->saveBarcode($request, $project, $product);

        return back()->with('status', 'Code-barres de '.$product->name.' enregistré.');
    }

    /** Modèle Excel de la liste standard totale. */
    public function template(): BinaryFileResponse
    {
        $spreadsheet = $this->catalog->template();
        $path = tempnam(storage_path('app'), 'liste-standard-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, 'modele-liste-standard.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** Commun Web / API. @return array{excluded: list<string>, restored: list<string>} */
    public function saveFacility(Request $request, Project $project, HealthFacility $facility): array
    {
        Gate::authorize('configure', $project);
        abort_unless($this->lists->facilities($project)->contains('id', $facility->id), 404);
        $data = $request->validate(['retained' => ['nullable', 'array'], 'retained.*' => ['uuid']]);
        $result = $this->catalog->saveFacilitySelection($facility, $data['retained'] ?? [], $request->user());
        $this->audit->record($request, 'health_facility.standard_list.updated', $facility, [], [
            'project_id' => $project->id, 'excluded' => count($result['excluded']), 'restored' => count($result['restored']),
        ]);

        return $result;
    }

    /** Commun Web / API. */
    public function createProduct(Request $request, Project $project, string $prefix): Product
    {
        Gate::authorize('configure', $project);
        $data = $request->validate(...StandardListCatalogService::productRules($prefix));
        $product = $this->catalog->createProduct($project->organization, $prefix === '' ? $data : data_get($data, rtrim($prefix, '.')));
        $this->catalog->appendToProjectList($project, [$product->id], $request->user()->id);
        $this->audit->record($request, 'product.created', $product, [], $product->only(['organization_id', 'code', 'name', 'packaging']));

        return $product;
    }

    /** Commun Web / API. */
    public function saveBarcode(Request $request, Project $project, Product $product): void
    {
        Gate::authorize('configure', $project);
        abort_unless($product->organization_id === $project->organization_id, 404);
        $data = $request->validate(['barcode' => ['nullable', 'string', 'max:190']], [], ['barcode' => 'code-barres']);
        $old = $product->codes()->where('code_type', 'barcode')->value('value');
        $this->catalog->setBarcode($product, $data['barcode'] ?? null);
        $this->audit->record($request, 'product.barcode.updated', $product, ['barcode' => $old], ['barcode' => $product->codes()->where('code_type', 'barcode')->value('value')]);
    }
}
