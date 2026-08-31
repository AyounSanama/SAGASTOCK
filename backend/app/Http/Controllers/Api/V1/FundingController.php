<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Donor;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FundingController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $this->projectIn($organization, $project);
        return response()->json([
            'donors' => $organization->donors()->orderBy('name')->get(),
            'programs' => $organization->programs()->with('donor:id,code,name')->orderBy('name')->get(),
            'project_donors' => $project->donors()->orderBy('name')->get(),
            'project_programs' => $project->programs()->with('donor:id,code,name')->orderBy('name')->get(),
        ]);
    }

    public function storeDonor(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('donors')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:180'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $donor = $organization->donors()->create($data);
        $this->audit->record($request, 'donor.created', $donor, [], $donor->only(['organization_id', 'code', 'name']));
        return response()->json(['donor' => $donor], 201);
    }

    public function storeProgram(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $data = $request->validate([
            'donor_id' => ['nullable', 'uuid', 'exists:donors,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('programs')->where('organization_id', $organization->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (! empty($data['donor_id'])) $this->donorIn($organization, Donor::findOrFail($data['donor_id']));
        $program = $organization->programs()->create($data);
        $this->audit->record($request, 'program.created', $program, [], $program->only(['organization_id', 'donor_id', 'code', 'name']));
        return response()->json(['program' => $program->load('donor:id,code,name')], 201);
    }

    public function updateDonor(Request $request, Organization $organization, Donor $donor): JsonResponse
    {
        $this->accessible($request, $organization); $this->donorIn($organization, $donor);
        $data = $request->validate([
            'code' => ['required','alpha_dash','max:50',Rule::unique('donors')->where('organization_id',$organization->id)->ignore($donor->id)],
            'name' => ['required','string','max:180'], 'email' => ['nullable','email','max:190'],
            'phone' => ['nullable','string','max:40'], 'is_active' => ['sometimes','boolean'],
        ]);
        $old=$donor->toArray(); $donor->update($data);
        $this->audit->record($request,'donor.updated',$donor,$old,$donor->fresh()->toArray());
        return response()->json(['donor'=>$donor]);
    }

    public function archiveDonor(Request $request, Organization $organization, Donor $donor): JsonResponse
    {
        $this->accessible($request,$organization); $this->donorIn($organization,$donor);
        $donor->update(['is_active'=>false]); $donor->delete(); $this->audit->record($request,'donor.archived',$donor);
        return response()->json(status:204);
    }

    public function restoreDonor(Request $request, Organization $organization, string $donor): JsonResponse
    {
        $this->accessible($request,$organization); $model=$organization->donors()->onlyTrashed()->findOrFail($donor);
        $model->restore(); $model->update(['is_active'=>true]); $this->audit->record($request,'donor.restored',$model);
        return response()->json(['donor'=>$model]);
    }

    public function updateProgram(Request $request, Organization $organization, Program $program): JsonResponse
    {
        $this->accessible($request,$organization); abort_unless($program->organization_id===$organization->id,404);
        $data=$request->validate([
            'donor_id'=>['nullable','uuid','exists:donors,id'],'code'=>['required','alpha_dash','max:50',Rule::unique('programs')->where('organization_id',$organization->id)->ignore($program->id)],
            'name'=>['required','string','max:180'],'description'=>['nullable','string','max:3000'],'starts_on'=>['nullable','date'],'ends_on'=>['nullable','date','after_or_equal:starts_on'],'is_active'=>['sometimes','boolean'],
        ]);
        if(!empty($data['donor_id']))$this->donorIn($organization,Donor::findOrFail($data['donor_id']));
        $old=$program->toArray();$program->update($data);$this->audit->record($request,'program.updated',$program,$old,$program->fresh()->toArray());
        return response()->json(['program'=>$program->load('donor:id,code,name')]);
    }

    public function archiveProgram(Request $request, Organization $organization, Program $program): JsonResponse
    {
        $this->accessible($request,$organization);abort_unless($program->organization_id===$organization->id,404);
        $program->update(['is_active'=>false]);$program->delete();$this->audit->record($request,'program.archived',$program);
        return response()->json(status:204);
    }

    public function restoreProgram(Request $request, Organization $organization, string $program): JsonResponse
    {
        $this->accessible($request,$organization);$model=$organization->programs()->onlyTrashed()->findOrFail($program);
        $model->restore();$model->update(['is_active'=>true]);$this->audit->record($request,'program.restored',$model);
        return response()->json(['program'=>$model->load('donor:id,code,name')]);
    }

    public function attachDonor(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $this->projectIn($organization, $project);
        $data = $request->validate([
            'donor_id' => ['required', 'uuid', 'exists:donors,id'],
            'funding_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'agreement_reference' => ['nullable', 'string', 'max:120'],
        ]);
        $donor = Donor::findOrFail($data['donor_id']);
        $this->donorIn($organization, $donor);
        $project->donors()->syncWithoutDetaching([$donor->id => [
            'funding_amount' => $data['funding_amount'] ?? null,
            'currency' => isset($data['currency']) ? strtoupper($data['currency']) : null,
            'agreement_reference' => $data['agreement_reference'] ?? null,
        ]]);
        $this->audit->record($request, 'project.donor_attached', $project, [], ['donor_id' => $donor->id]);
        return response()->json(['project_donors' => $project->donors]);
    }

    public function attachProgram(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $this->projectIn($organization, $project);
        $data = $request->validate(['program_id' => ['required', 'uuid', 'exists:programs,id']]);
        $program = Program::findOrFail($data['program_id']);
        abort_unless($program->organization_id === $organization->id, 422, 'Le programme ne dépend pas de cette organisation.');
        $project->programs()->syncWithoutDetaching([$program->id]);
        $this->audit->record($request, 'project.program_attached', $project, [], ['program_id' => $program->id]);
        return response()->json(['project_programs' => $project->programs()->with('donor:id,code,name')->get()]);
    }

    public function detachDonor(Request $request, Organization $organization, Project $project, Donor $donor): JsonResponse
    {
        $this->accessible($request,$organization);$this->projectIn($organization,$project);$this->donorIn($organization,$donor);
        $project->donors()->detach($donor->id);$this->audit->record($request,'project.donor_detached',$project,[],['donor_id'=>$donor->id]);
        return response()->json(status:204);
    }

    public function detachProgram(Request $request, Organization $organization, Project $project, Program $program): JsonResponse
    {
        $this->accessible($request,$organization);$this->projectIn($organization,$project);abort_unless($program->organization_id===$organization->id,404);
        $project->programs()->detach($program->id);$this->audit->record($request,'project.program_detached',$project,[],['program_id'=>$program->id]);
        return response()->json(status:204);
    }

    private function projectIn(Organization $organization, Project $project): void
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($this->scopes->projects(request()->user())->whereKey($project->id)->exists(), 404);
    }

    private function donorIn(Organization $organization, Donor $donor): void
    {
        abort_unless($donor->organization_id === $organization->id, 422, 'Le bailleur ne dépend pas de cette organisation.');
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
