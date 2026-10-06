<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FundingController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function home(Request $request): View
    {
        $this->allow('funding.view');
        $organizations = $this->scopes->organizations($request->user())->orderBy('name')->get();
        $organization = $request->filled('organization_id')
            ? $organizations->firstWhere('id', $request->string('organization_id')->toString())
            : $organizations->first();
        abort_if($request->filled('organization_id') && ! $organization, 404);

        $projects = $organization
            ? $this->scopes->projects($request->user())
                ->where('organization_id', $organization->id)->with('mission:id,name')->orderBy('name')->get()
            : collect();
        $project = $request->filled('project_id')
            ? $projects->firstWhere('id', $request->string('project_id')->toString())
            : $projects->first();
        abort_if($request->filled('project_id') && ! $project, 404);

        return view('funding.index', $this->viewData($request, $organization, $project, $organizations, $projects));
    }

    public function index(Request $request, Organization $organization, Project $project): View
    {
        $this->allow('funding.view');
        $this->accessible($request, $organization);
        $this->projectIn($organization, $project);
        return view('funding.index', $this->viewData(
            $request,
            $organization,
            $project,
            collect([$organization]),
            collect([$project]),
        ));
    }

    public function storeDonor(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('funding.manage');
        $this->accessible($request, $organization);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('donors')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:180'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'string', 'max:40'],
        ]);
        $donor = $organization->donors()->create([...$data, 'is_active' => true]);
        $this->audit->record($request, 'donor.created', $donor, [], $donor->only(['organization_id', 'code', 'name']));
        return back()->with('status', 'Bailleur créé.');
    }

    public function storeProgram(Request $request, Organization $organization): RedirectResponse
    {
        $this->allow('funding.manage');
        $this->accessible($request, $organization);
        $data = $request->validate([
            'donor_id' => ['nullable', 'uuid', 'exists:donors,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('programs')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ]);
        if (! empty($data['donor_id'])) abort_unless(Donor::whereKey($data['donor_id'])->where('organization_id', $organization->id)->exists(), 422);
        $program = $organization->programs()->create([...$data, 'is_active' => true]);
        $this->audit->record($request, 'program.created', $program, [], $program->only(['organization_id', 'donor_id', 'code', 'name']));
        return back()->with('status', 'Programme créé.');
    }

    public function updateDonor(Request $request, Organization $organization, Donor $donor): RedirectResponse
    {
        $this->allow('funding.manage');$this->accessible($request,$organization);abort_unless($donor->organization_id===$organization->id,404);
        $data=$request->validate(['code'=>['required','alpha_dash','max:50',Rule::unique('donors')->where('organization_id',$organization->id)->ignore($donor->id)],'name'=>['required','string','max:180'],'email'=>['nullable','email'],'phone'=>['nullable','string','max:40'],'is_active'=>['nullable','boolean']]);
        $old=$donor->toArray();$data['is_active']=$request->boolean('is_active');$donor->update($data);$this->audit->record($request,'donor.updated',$donor,$old,$donor->fresh()->toArray());
        return back()->with('status','Bailleur mis à jour.');
    }
    public function archiveDonor(Request $request, Organization $organization, Donor $donor):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);abort_unless($donor->organization_id===$organization->id,404);$donor->update(['is_active'=>false]);$donor->delete();$this->audit->record($request,'donor.archived',$donor);return back()->with('status','Bailleur archivé.');}
    public function restoreDonor(Request $request,Organization $organization,string $donor):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);$model=$organization->donors()->onlyTrashed()->findOrFail($donor);$model->restore();$model->update(['is_active'=>true]);$this->audit->record($request,'donor.restored',$model);return back()->with('status','Bailleur restauré.');}
    public function updateProgram(Request $request,Organization $organization,Program $program):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);abort_unless($program->organization_id===$organization->id,404);$data=$request->validate(['donor_id'=>['nullable','uuid','exists:donors,id'],'code'=>['required','alpha_dash','max:50',Rule::unique('programs')->where('organization_id',$organization->id)->ignore($program->id)],'name'=>['required','string','max:180'],'description'=>['nullable','string','max:3000'],'starts_on'=>['nullable','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'is_active'=>['nullable','boolean']]);if(!empty($data['donor_id']))abort_unless($organization->donors()->whereKey($data['donor_id'])->exists(),422);$old=$program->toArray();$data['is_active']=$request->boolean('is_active');$program->update($data);$this->audit->record($request,'program.updated',$program,$old,$program->fresh()->toArray());return back()->with('status','Programme mis à jour.');}
    public function archiveProgram(Request $request,Organization $organization,Program $program):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);abort_unless($program->organization_id===$organization->id,404);$program->update(['is_active'=>false]);$program->delete();$this->audit->record($request,'program.archived',$program);return back()->with('status','Programme archivé.');}
    public function restoreProgram(Request $request,Organization $organization,string $program):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);$model=$organization->programs()->onlyTrashed()->findOrFail($program);$model->restore();$model->update(['is_active'=>true]);$this->audit->record($request,'program.restored',$model);return back()->with('status','Programme restauré.');}

    public function attachDonor(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('funding.manage'); $this->accessible($request, $organization); $this->projectIn($organization, $project);
        $data = $request->validate(['donor_id' => ['required', 'uuid', 'exists:donors,id'], 'funding_amount' => ['nullable', 'numeric', 'min:0'], 'currency' => ['nullable', 'string', 'size:3'], 'agreement_reference' => ['nullable', 'string', 'max:120']]);
        abort_unless(Donor::whereKey($data['donor_id'])->where('organization_id', $organization->id)->exists(), 422);
        $project->donors()->syncWithoutDetaching([$data['donor_id'] => ['funding_amount' => $data['funding_amount'] ?? null, 'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null, 'agreement_reference' => $data['agreement_reference'] ?? null]]);
        $this->audit->record($request, 'project.donor_attached', $project, [], ['donor_id' => $data['donor_id']]);
        return back()->with('status', 'Bailleur associé au projet.');
    }

    public function attachProgram(Request $request, Organization $organization, Project $project): RedirectResponse
    {
        $this->allow('funding.manage'); $this->accessible($request, $organization); $this->projectIn($organization, $project);
        $data = $request->validate(['program_id' => ['required', 'uuid', 'exists:programs,id']]);
        abort_unless(Program::whereKey($data['program_id'])->where('organization_id', $organization->id)->exists(), 422);
        $project->programs()->syncWithoutDetaching([$data['program_id']]);
        $this->audit->record($request, 'project.program_attached', $project, [], ['program_id' => $data['program_id']]);
        return back()->with('status', 'Programme associé au projet.');
    }

    public function detachDonor(Request $request,Organization $organization,Project $project,Donor $donor):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);$this->projectIn($organization,$project);abort_unless($donor->organization_id===$organization->id,404);$project->donors()->detach($donor->id);$this->audit->record($request,'project.donor_detached',$project,[],['donor_id'=>$donor->id]);return back()->with('status','Bailleur dissocié.');}
    public function detachProgram(Request $request,Organization $organization,Project $project,Program $program):RedirectResponse{$this->allow('funding.manage');$this->accessible($request,$organization);$this->projectIn($organization,$project);abort_unless($program->organization_id===$organization->id,404);$project->programs()->detach($program->id);$this->audit->record($request,'project.program_detached',$project,[],['program_id'=>$program->id]);return back()->with('status','Programme dissocié.');}

    private function projectIn(Organization $organization, Project $project): void
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects(request()->user())->whereKey($project->id)->exists(), 404);
    }
    private function allow(string $permission): void { abort_unless(Auth::user()?->hasPermission($permission), 403); }
    private function accessible(Request $request, Organization $organization): void { abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404); }

    private function viewData(Request $request, ?Organization $organization, ?Project $project, $organizations, $projects): array
    {
        $canManage = $request->user()->hasPermission('funding.manage');
        $activeSection = in_array($request->string('section')->toString(), ['donors', 'programs'], true)
            ? $request->string('section')->toString()
            : 'donors';
        // Niveau 2 : « Référentiels > Bailleurs » de la Coordination ; le
        // rattachement au projet se fait dans l'assistant de création.
        $referentialsMode = app(\App\Services\GovernanceService::class)->roleCode($request->user()) === \App\Services\GovernanceService::COORDINATION_ADMIN;
        if ($referentialsMode) {
            $activeSection = 'donors';
        }

        return [
            'referentialsMode' => $referentialsMode,
            'organization' => $organization,
            'project' => $project,
            'organizations' => $organizations,
            'projects' => $projects,
            'canManage' => $canManage,
            'activeSection' => $activeSection,
            'donors' => $organization?->donors()->withCount(['projects', 'programs'])->orderBy('name')->get() ?? collect(),
            'programs' => $organization?->programs()->with(['donor:id,code,name'])->withCount('projects')->orderBy('name')->get() ?? collect(),
            'assignedDonors' => $project?->donors()->orderBy('name')->get() ?? collect(),
            'assignedPrograms' => $project?->programs()->with('donor:id,code,name')->orderBy('name')->get() ?? collect(),
            'archivedDonors' => $organization?->donors()->onlyTrashed()->latest('deleted_at')->get() ?? collect(),
            'archivedPrograms' => $organization?->programs()->onlyTrashed()->latest('deleted_at')->get() ?? collect(),
        ];
    }
}
