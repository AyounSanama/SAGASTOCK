<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveHealthFacilityRequest;
use App\Models\HealthFacility;
use App\Services\AuditService;
use App\Services\HealthFacilityConfigurationService;
use App\Services\HealthFacilityManagementService;
use App\Services\ProjectAdminWorkspaceService;
use App\Services\StandardListGenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Niveau 5 — Écrans Web de l'Admin Projet (maquettes AdminProjet 01 à 04). */
class ProjectAdminController extends Controller
{
    private const PER_PAGE = 8;

    public function __construct(
        private ProjectAdminWorkspaceService $workspace,
        private HealthFacilityManagementService $management,
        private HealthFacilityConfigurationService $configuration,
        private AuditService $audit,
    ) {}

    /** Maquette 01 — Tableau de bord. */
    public function dashboard(Request $request): View
    {
        $board = $this->workspace->dashboard($request->user());

        return view('project-admin.dashboard', [
            'project' => $board['project'],
            'board' => $board,
            'syncedAt' => $board['last_sync_at'] ? \Illuminate\Support\Carbon::parse($board['last_sync_at']) : false,
        ]);
    }

    /** Maquette 02 — « Projet & FOSA », onglet FOSA. */
    public function facilities(Request $request): View
    {
        $project = $this->workspace->project($request->user());
        $all = $this->workspace->facilities($project);
        $accounts = $this->workspace->accountsByFacility($all, $this->workspace->accounts($all));
        $search = mb_strtolower($request->string('search')->trim()->toString());
        $filtered = $all
            ->when($search !== '', fn ($items) => $items->filter(fn (HealthFacility $facility) => str_contains(mb_strtolower($facility->name.' '.$facility->code), $search)))
            ->when($request->filled('category'), fn ($items) => $items->where('facility_category_id', $request->string('category')->toString()))
            ->when($request->filled('status'), fn ($items) => $items->filter(fn (HealthFacility $facility) => ProjectAdminWorkspaceService::status($facility)['key'] === $request->string('status')->toString()))
            ->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator($filtered->forPage($page, self::PER_PAGE)->values(), $filtered->count(), self::PER_PAGE, $page, [
            'path' => $request->url(), 'query' => $request->query(),
        ]);

        return view('project-admin.facilities', [
            'project' => $project,
            'facilities' => $paginator,
            'accounts' => $accounts,
            'categories' => $all->pluck('facilityCategory')->filter()->unique('id')->sortBy('name')->values(),
            'counts' => $this->counts($all),
        ]);
    }

    /** « Paramètres d'approvisionnement » : valeurs du projet (Coordination) et de chaque FOSA. */
    public function supply(Request $request): View
    {
        $project = $this->workspace->project($request->user());
        $facilities = $this->workspace->facilities($project);

        return view('project-admin.supply', ['project' => $project, 'facilities' => $facilities, 'counts' => $this->counts($facilities)]);
    }

    /** Maquette 03 — Configuration d'une nouvelle FOSA. */
    public function create(Request $request): View
    {
        abort_unless($request->user()->hasPermission('health_facilities.manage') || $request->user()->hasPermission('structures.manage'), 403);

        return $this->form($request, null);
    }

    public function edit(Request $request, HealthFacility $facility): View
    {
        Gate::authorize('update', $facility);

        return $this->form($request, $facility->load(['targetPopulations:id', 'pathologies:id']));
    }

    public function store(SaveHealthFacilityRequest $request): RedirectResponse
    {
        $facility = $this->management->declare($request, $request->organization());
        $this->audit->record($request, 'facility.created', $facility, [], $facility->toArray());

        return redirect()->route('modules.health-facilities')
            ->with('status', "FOSA « {$facility->name} » déclarée : elle attend la validation de la Coordination.");
    }

    public function update(SaveHealthFacilityRequest $request, HealthFacility $facility): RedirectResponse
    {
        $old = $facility->toArray();
        $this->management->update($request, $facility);
        $this->audit->record($request, 'facility.updated', $facility, $old, $facility->fresh()->toArray());

        return redirect()->route('project-admin.facilities.edit', $facility)->with('status', 'Configuration de la FOSA enregistrée.');
    }

    /** Nombre de produits de la Liste Standard pour les choix en cours (panneau « Liste Standard générée »). */
    public function preview(Request $request): JsonResponse
    {
        $project = $this->workspace->project($request->user());
        $data = $request->validate([
            'care_level_id' => ['nullable', 'uuid'],
            'facility_category_id' => ['nullable', 'uuid'],
            'target_population_ids' => ['nullable', 'array'], 'target_population_ids.*' => ['uuid'],
            'pathology_ids' => ['nullable', 'array'], 'pathology_ids.*' => ['uuid'],
        ]);
        if (empty($data['care_level_id'])) {
            return response()->json(['count' => null]);
        }
        $generator = app(StandardListGenerationService::class);
        $products = $generator->generate($project, $data);
        $published = $generator->publishedProductIds($project);

        return response()->json(['count' => ($published === null ? $products : $products->whereIn('id', $published))->count()]);
    }

    /** Maquette 04 — Liste Standard en consultation (projet ou FOSA). */
    public function standardList(Request $request): View
    {
        $project = $this->workspace->project($request->user());
        $facilities = $this->workspace->facilities($project)->filter(fn (HealthFacility $facility) => $facility->validation_status !== HealthFacility::STATUS_REFUSED)->values();
        $facility = $request->filled('facility') ? $facilities->firstWhere('id', $request->string('facility')->toString()) : null;
        abort_if($request->filled('facility') && ! $facility, 404);

        return view('project-admin.standard-list', [
            'project' => $project,
            'facilities' => $facilities,
            'list' => $this->workspace->standardList($project, $facility),
        ]);
    }

    public function exportStandardList(Request $request): BinaryFileResponse
    {
        $project = $this->workspace->project($request->user());
        $facility = $request->filled('facility')
            ? $this->workspace->facilities($project)->firstWhere('id', $request->string('facility')->toString())
            : null;
        abort_if($request->filled('facility') && ! $facility, 404);
        $list = $this->workspace->standardList($project, $facility);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Liste Standard');
        $sheet->fromArray(['Liste Standard · '.$list['title'].($facility ? ' · '.$facility->name : '')], null, 'A1');
        $sheet->fromArray(['Code', 'Désignation', 'Conditionnement', 'Pathologie / activité', 'Retenu'], null, 'A3');
        $row = 4;
        foreach ($list['rows'] as $item) {
            $product = $item['product'];
            $sheet->fromArray([
                $product->code, trim($product->name.' '.$product->strength),
                $product->packaging ?: collect([$product->dosageForm?->name, $product->baseUnit?->name])->filter()->join(', '),
                $item['pathology'] ?? '', $item['retained'] ? 'Oui' : 'Non',
            ], null, "A{$row}");
            $row++;
        }
        $sheet->freezePane('A4');
        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = tempnam(storage_path('app'), 'liste-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
        $this->audit->record($request, 'standard_list.exported', $project, [], ['facility_id' => $facility?->id]);

        return response()->download($path, 'liste-standard-'.$project->code.($facility ? '-'.$facility->code : '').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    private function form(Request $request, ?HealthFacility $facility): View
    {
        $project = $this->workspace->project($request->user());

        return view('project-admin.facility', [
            'project' => $project,
            'facility' => $facility,
            'facilityOptions' => $this->configuration->options($project),
            'standardListCount' => $facility ? $this->management->standardList($facility)->count() : null,
        ]);
    }

    /** Compteurs des onglets « FOSA » et « Comptes utilisateurs ». */
    private function counts($facilities): array
    {
        return ['facilities' => $facilities->count(), 'accounts' => $this->workspace->accounts($facilities)->count()];
    }
}
