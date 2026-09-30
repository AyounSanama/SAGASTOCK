<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatalogReference;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\CareLevelHierarchyService;
use App\Services\GovernanceService;
use App\Services\ProjectMedicalConfigurationService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Référentiel médical de la Coordination (AM-111) : hiérarchie
 * Niveau → Catégorie → Programme des niveaux de soins.
 *
 * Accessible depuis « Configuration des projets », seul module Web de
 * configuration ouvert à la Coordination en V1.
 */
class MedicalReferenceController extends Controller
{
    public function __construct(
        private UserScopeService $scopes,
        private CareLevelHierarchyService $hierarchy,
        private AuditService $audit,
        private ProjectMedicalConfigurationService $medical,
    ) {}

    public function show(Request $request): View
    {
        $organization = $this->organization($request);

        return view('projects.medical-references', [
            'organization' => $organization,
            'tree' => $this->hierarchy->tree($organization, activeOnly: false),
            'parents' => CatalogReference::where('reference_type', CareLevelHierarchyService::TYPE)
                ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
                ->where('is_active', true)->where('depth', '<', CareLevelHierarchyService::MAX_DEPTH)
                ->orderBy('depth')->orderBy('name')->get(['id', 'name', 'depth']),
            'levels' => CareLevelHierarchyService::DEPTH_LABELS,
            'populations' => $this->flat($organization, 'target_population'),
            'pathologies' => $this->flat($organization, 'pathology'),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function storeCareLevel(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($this->canManage($request), 403);
        $node = $this->hierarchy->createNode($organization, $request->validate($this->hierarchy->rules($organization)));
        $this->audit->record($request, 'reference.created', $node, [], $node->only(['code', 'name', 'parent_id', 'depth']));

        return back()->with('status', (CareLevelHierarchyService::DEPTH_LABELS[$node->depth] ?? 'Élément').' ajouté.');
    }

    /** AM-112 — Ajout d'une population cible ou d'une pathologie au référentiel de l'organisation. */
    public function storeReference(Request $request, string $type): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($this->canManage($request), 403);
        abort_unless(in_array($type, ProjectMedicalConfigurationService::REFERENCE_TYPES, true), 404);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:60', \Illuminate\Validation\Rule::unique('catalog_references')
                ->where(fn ($query) => $query->where('organization_id', $organization->id)->where('reference_type', $type))],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $reference = $this->medical->createReference($organization, $type, $data);
        $this->audit->record($request, 'reference.created', $reference, [], $reference->only(['reference_type', 'code', 'name']));

        return back()->with('status', $type === 'pathology' ? 'Pathologie ajoutée.' : 'Population cible ajoutée.');
    }

    public function archiveCareLevel(Request $request, CatalogReference $reference): RedirectResponse
    {
        $organization = $this->organization($request);
        abort_unless($this->canManage($request), 403);
        $this->hierarchy->archiveNode($organization, $reference);
        $this->audit->record($request, 'reference.archived', $reference);

        return back()->with('status', 'Élément archivé.');
    }

    private function organization(Request $request): Organization
    {
        return $this->scopes->organizations($request->user())
            ->where('is_active', true)->orderBy('name')->firstOrFail();
    }

    private function flat(Organization $organization, string $type)
    {
        return CatalogReference::where('reference_type', $type)
            ->where(fn ($query) => $query->whereNull('organization_id')->orWhere('organization_id', $organization->id))
            ->orderBy('name')->get(['id', 'organization_id', 'code', 'name', 'is_active']);
    }

    private function canManage(Request $request): bool
    {
        return app(GovernanceService::class)->roleCode($request->user()) === GovernanceService::COORDINATION_ADMIN
            && $request->user()->hasPermission('standard_lists.manage');
    }
}
