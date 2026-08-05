<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\ModuleActivation;
use App\Models\Organization;
use App\Models\Pharmacy;
use App\Models\Project;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StructureController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization): View
    {
        $this->allow($request, 'structures.view');
        $this->organization($request, $organization);
        $facilities = $organization->healthFacilities()->with(['mission.country', 'projects:id,name', 'departments', 'archivedDepartments', 'pharmacies.department', 'archivedPharmacies', 'sites.department', 'sites.pharmacy', 'archivedSites'])
            ->when($request->string('search')->toString(), fn ($query, $search) => $query->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(12)->withQueryString();
        $archivedFacilities = $organization->healthFacilities()->onlyTrashed()->latest('deleted_at')->get();
        $activations = ModuleActivation::where(function ($query) use ($organization) {
            $query->where(fn ($q) => $q->where('target_type', 'organization')->where('target_id', $organization->id))
                ->orWhere(fn ($q) => $q->where('target_type', 'project')->whereIn('target_id', $organization->projects()->pluck('id')))
                ->orWhere(fn ($q) => $q->where('target_type', 'facility')->whereIn('target_id', $organization->healthFacilities()->pluck('id')));
        })->get()->keyBy(fn ($item) => "{$item->target_type}:{$item->target_id}:{$item->module_code}");
        return view('structures.index', [
            'organization' => $organization, 'facilities' => $facilities,
            'archivedFacilities' => $archivedFacilities, 'activations' => $activations,
            'missions' => $organization->missions()->with('country')->orderBy('name')->get(),
            'projects' => $organization->projects()->orderBy('name')->get(),
            'moduleCodes' => [
                'references' => ['name' => 'Gestion des médicaments', 'icon' => '💊', 'description' => 'Catalogue, référentiels et produits médicaux.'],
                'stocks' => ['name' => 'Stock de médicaments', 'icon' => '▤', 'description' => 'Lots, mouvements, niveaux et traçabilité.'],
                'receptions' => ['name' => 'Réceptions pharmaceutiques', 'icon' => '↓', 'description' => 'Contrôle et enregistrement des réceptions.'],
                'dispensing' => ['name' => 'Dispensation des médicaments', 'icon' => '✚', 'description' => 'Remise sécurisée des traitements aux patients.'],
                'inventories' => ['name' => 'Inventaires de pharmacie', 'icon' => '✓', 'description' => 'Comptages physiques et rapprochements.'],
                'orders' => ['name' => 'Commandes de réapprovisionnement', 'icon' => '🛒', 'description' => 'Besoins, commandes et circuits d’approbation.'],
                'alerts' => ['name' => 'Alertes et notifications', 'icon' => '!', 'description' => 'Stock faible, péremptions et événements critiques.'],
                'reports' => ['name' => 'Rapports et statistiques', 'icon' => '↗', 'description' => 'Indicateurs, analyses et rapports opérationnels.'],
            ],
        ]);
    }

    public function createFacility(Request $request, Organization $organization): View
    {
        $this->manage($request, $organization);

        return view('structures.create', [
            'organization' => $organization,
            'missions' => $organization->missions()->with('country')->orderBy('name')->get(),
            'projects' => $organization->projects()->orderBy('name')->get(),
        ]);
    }

    public function storeFacility(Request $request, Organization $organization): RedirectResponse
    {
        $this->manage($request, $organization);
        $data = $this->facilityData($request, $organization);
        $projects = $data['project_ids'] ?? []; unset($data['project_ids']);
        $facility = $organization->healthFacilities()->create($data);
        $facility->projects()->sync($projects);
        $this->audit->record($request, 'facility.created', $facility, [], $facility->toArray());
        return redirect()->route('organizations.structures.index', [$organization, 'facility' => $facility->id])
            ->with('status', 'Formation sanitaire créée avec succès.');
    }

    public function updateFacility(Request $request, Organization $organization, HealthFacility $facility): RedirectResponse
    {
        $this->manageFacility($request, $organization, $facility);
        $data = $this->facilityData($request, $organization, $facility);
        $projects = $data['project_ids'] ?? []; unset($data['project_ids']);
        $old = $facility->toArray(); $facility->update($data); $facility->projects()->sync($projects);
        $this->audit->record($request, 'facility.updated', $facility, $old, $facility->fresh()->toArray());
        return back()->with('status', 'Formation sanitaire mise à jour.');
    }

    public function archiveFacility(Request $request, Organization $organization, HealthFacility $facility): RedirectResponse
    {
        $this->manageFacility($request, $organization, $facility);
        $facility->update(['is_active' => false]); $facility->delete();
        $this->audit->record($request, 'facility.archived', $facility);
        return back()->with('status', 'Formation sanitaire archivée.');
    }

    public function restoreFacility(Request $request, Organization $organization, string $facility): RedirectResponse
    {
        $this->manage($request, $organization);
        $model = $organization->healthFacilities()->onlyTrashed()->findOrFail($facility);
        $model->restore(); $model->update(['is_active' => true]);
        $this->audit->record($request, 'facility.restored', $model);
        return back()->with('status', 'Formation sanitaire restaurée.');
    }

    public function storeDepartment(Request $request, Organization $organization, HealthFacility $facility): RedirectResponse
    {
        $this->manageFacility($request, $organization, $facility);
        $model = $facility->departments()->create($this->departmentData($request, $facility));
        return $this->saved($request, $model, 'department.created', 'Département créé.');
    }
    public function updateDepartment(Request $request, Organization $organization, HealthFacility $facility, Department $department): RedirectResponse
    {
        $this->manageChild($request, $organization, $facility, $department);
        $department->update($this->departmentData($request, $facility, $department));
        return $this->saved($request, $department, 'department.updated', 'Département mis à jour.');
    }
    public function archiveDepartment(Request $request, Organization $organization, HealthFacility $facility, Department $department): RedirectResponse
    { return $this->archiveChild($request, $organization, $facility, $department, 'department.archived', 'Département archivé.'); }
    public function restoreDepartment(Request $request, Organization $organization, HealthFacility $facility, string $department): RedirectResponse
    { return $this->restoreChild($request, $organization, $facility, Department::onlyTrashed()->findOrFail($department), 'department.restored', 'Département restauré.'); }

    public function storePharmacy(Request $request, Organization $organization, HealthFacility $facility): RedirectResponse
    {
        $this->manageFacility($request, $organization, $facility);
        $model = $facility->pharmacies()->create($this->pharmacyData($request, $facility));
        return $this->saved($request, $model, 'pharmacy.created', 'Pharmacie créée.');
    }
    public function updatePharmacy(Request $request, Organization $organization, HealthFacility $facility, Pharmacy $pharmacy): RedirectResponse
    {
        $this->manageChild($request, $organization, $facility, $pharmacy);
        $pharmacy->update($this->pharmacyData($request, $facility, $pharmacy));
        return $this->saved($request, $pharmacy, 'pharmacy.updated', 'Pharmacie mise à jour.');
    }
    public function archivePharmacy(Request $request, Organization $organization, HealthFacility $facility, Pharmacy $pharmacy): RedirectResponse
    { return $this->archiveChild($request, $organization, $facility, $pharmacy, 'pharmacy.archived', 'Pharmacie archivée.'); }
    public function restorePharmacy(Request $request, Organization $organization, HealthFacility $facility, string $pharmacy): RedirectResponse
    { return $this->restoreChild($request, $organization, $facility, Pharmacy::onlyTrashed()->findOrFail($pharmacy), 'pharmacy.restored', 'Pharmacie restaurée.'); }

    public function storeSite(Request $request, Organization $organization, HealthFacility $facility): RedirectResponse
    {
        $this->manageFacility($request, $organization, $facility);
        $model = $facility->sites()->create([
            ...$this->siteData($request, $facility),
            'organization_id' => $organization->id,
        ]);
        return $this->saved($request, $model, 'site.created', 'Site créé.');
    }
    public function updateSite(Request $request, Organization $organization, HealthFacility $facility, Site $site): RedirectResponse
    {
        $this->manageChild($request, $organization, $facility, $site);
        $site->update($this->siteData($request, $facility, $site));
        return $this->saved($request, $site, 'site.updated', 'Site mis à jour.');
    }
    public function archiveSite(Request $request, Organization $organization, HealthFacility $facility, Site $site): RedirectResponse
    { return $this->archiveChild($request, $organization, $facility, $site, 'site.archived', 'Site archivé.'); }
    public function restoreSite(Request $request, Organization $organization, HealthFacility $facility, string $site): RedirectResponse
    { return $this->restoreChild($request, $organization, $facility, Site::onlyTrashed()->findOrFail($site), 'site.restored', 'Site restauré.'); }

    public function activation(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow($request, 'modules.manage'); $this->organization($request, $organization);
        $data = $request->validate([
            'target' => ['required', 'string'], 'module_code' => ['required', Rule::in(['references','stocks','receptions','dispensing','inventories','orders','alerts','reports'])],
            'is_enabled' => ['required', 'boolean'],
        ]);
        [$type, $id] = array_pad(explode(':', $data['target'], 2), 2, null);
        abort_unless($id && in_array($type, ['organization','project','facility'], true), 422);
        $valid = match($type) {
            'organization' => $id === $organization->id,
            'project' => $organization->projects()->whereKey($id)->exists(),
            'facility' => $organization->healthFacilities()->whereKey($id)->exists(),
        };
        abort_unless($valid, 422, 'Périmètre invalide.');
        $activation = ModuleActivation::updateOrCreate(
            ['target_type'=>$type,'target_id'=>$id,'module_code'=>$data['module_code']],
            ['is_enabled'=>$data['is_enabled'],'updated_by'=>$request->user()->id],
        );
        $this->audit->record($request, 'module.activation_updated', $activation, [], $activation->toArray());
        return back()->with('status', 'Activation du module mise à jour.');
    }

    private function facilityData(Request $request, Organization $organization, ?HealthFacility $facility=null): array
    {
        $data=$request->validate([
            'mission_id'=>['nullable','uuid','exists:missions,id'],'project_ids'=>['nullable','array'],'project_ids.*'=>['uuid','exists:projects,id'],
            'code'=>['required','alpha_dash','max:50',Rule::unique('health_facilities')->where('organization_id',$organization->id)->ignore($facility?->id)],
            'name'=>['required','string','max:180'],'facility_type'=>['required',Rule::in(['hospital','health_center','clinic','warehouse','community','other'])],
            'care_level'=>['nullable','string','max:50'],'email'=>['nullable','email','max:190'],'phone'=>['nullable','string','max:40'],'address'=>['nullable','string','max:1000'],'is_active'=>['nullable','boolean'],
        ]);
        if(!empty($data['mission_id']))abort_unless(Mission::whereKey($data['mission_id'])->where('organization_id',$organization->id)->exists(),422);
        if(!empty($data['project_ids']))abort_unless(Project::whereIn('id',$data['project_ids'])->where('organization_id',$organization->id)->count()===count(array_unique($data['project_ids'])),422);
        $data['is_active']=$request->boolean('is_active',true); return $data;
    }
    private function departmentData(Request $request, HealthFacility $facility, ?Department $model=null):array{$data=$request->validate(['code'=>['required','alpha_dash','max:50',Rule::unique('departments')->where('health_facility_id',$facility->id)->ignore($model?->id)],'name'=>['required','string','max:160'],'department_type'=>['required',Rule::in(['clinical','pharmacy','laboratory','logistics','administration','other'])],'is_active'=>['nullable','boolean']]);$data['is_active']=$request->boolean('is_active',true);return $data;}
    private function pharmacyData(Request $request, HealthFacility $facility, ?Pharmacy $model=null):array{$data=$request->validate(['department_id'=>['nullable','uuid','exists:departments,id'],'code'=>['required','alpha_dash','max:50',Rule::unique('pharmacies')->where('health_facility_id',$facility->id)->ignore($model?->id)],'name'=>['required','string','max:160'],'pharmacy_type'=>['required',Rule::in(['central','hospital','dispensary','community','other'])],'is_active'=>['nullable','boolean']]);if(!empty($data['department_id']))abort_unless($facility->departments()->whereKey($data['department_id'])->exists(),422);$data['is_active']=$request->boolean('is_active',true);return $data;}
    private function siteData(Request $request, HealthFacility $facility, ?Site $model=null):array{$data=$request->validate(['department_id'=>['nullable','uuid','exists:departments,id'],'pharmacy_id'=>['nullable','uuid','exists:pharmacies,id'],'code'=>['required','alpha_dash','max:50',Rule::unique('sites')->where('health_facility_id',$facility->id)->ignore($model?->id)],'name'=>['required','string','max:160'],'site_type'=>['required',Rule::in(['stock','dispensing','stock_and_dispensing','quarantine','other'])],'location'=>['nullable','string','max:190'],'is_active'=>['nullable','boolean']]);if(!empty($data['department_id']))abort_unless($facility->departments()->whereKey($data['department_id'])->exists(),422);if(!empty($data['pharmacy_id']))abort_unless($facility->pharmacies()->whereKey($data['pharmacy_id'])->exists(),422);$data['is_active']=$request->boolean('is_active',true);return $data;}
    private function organization(Request $request,Organization $organization):void{abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(),404);}
    private function manage(Request $request,Organization $organization):void{$this->allow($request,'structures.manage');$this->organization($request,$organization);}
    private function manageFacility(Request $request,Organization $organization,HealthFacility $facility):void{$this->manage($request,$organization);abort_unless($facility->organization_id===$organization->id,404);}
    private function manageChild(Request $request,Organization $organization,HealthFacility $facility,Model $model):void{$this->manageFacility($request,$organization,$facility);abort_unless($model->health_facility_id===$facility->id,404);}
    private function saved(Request $request,Model $model,string $event,string $message):RedirectResponse{$this->audit->record($request,$event,$model,[],$model->toArray());return back()->with('status',$message);}
    private function archiveChild(Request $request,Organization $organization,HealthFacility $facility,Model $model,string $event,string $message):RedirectResponse{$this->manageChild($request,$organization,$facility,$model);$model->update(['is_active'=>false]);$model->delete();return $this->saved($request,$model,$event,$message);}
    private function restoreChild(Request $request,Organization $organization,HealthFacility $facility,Model $model,string $event,string $message):RedirectResponse{$this->manageChild($request,$organization,$facility,$model);$model->restore();$model->update(['is_active'=>true]);return $this->saved($request,$model,$event,$message);}
    private function allow(Request $request,string $permission):void{abort_unless($request->user()?->hasPermission($permission),403);}
}
