<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Project;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $projects = $organization->projects()->with('mission.country:id,iso2,name')
            ->when($request->filled('mission_id'), fn ($query) => $query->where('mission_id', $request->string('mission_id')))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);
        return response()->json($projects);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $project = $organization->projects()->create($this->validated($request, $organization));
        $this->audit->record($request, 'project.created', $project, [], $project->only(['organization_id', 'mission_id', 'code', 'name', 'is_active']));
        return response()->json(['project' => $project->load('mission.country:id,iso2,name')], 201);
    }

    public function update(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        $old = $project->only(['mission_id', 'code', 'name', 'description', 'starts_on', 'ends_on', 'is_active']);
        $project->update($this->validated($request, $organization, $project));
        $this->audit->record($request, 'project.updated', $project, $old, $project->only(array_keys($old)));
        return response()->json(['project' => $project->load('mission.country:id,iso2,name')]);
    }

    public function destroy(Request $request, Organization $organization, Project $project): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        $project->delete();
        $this->audit->record($request, 'project.archived', $project);
        return response()->json(status: 204);
    }

    public function restore(Request $request, Organization $organization, string $project): JsonResponse
    {
        $this->accessible($request, $organization);
        $model = $organization->projects()->onlyTrashed()->findOrFail($project);
        $model->restore(); $model->update(['is_active' => true]);
        $this->audit->record($request, 'project.restored', $model);
        return response()->json(['project' => $model->load('mission.country')]);
    }

    private function validated(Request $request, Organization $organization, ?Project $project = null): array
    {
        $data = $request->validate([
            'mission_id' => ['required', 'uuid', 'exists:missions,id'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('projects')->where('organization_id', $organization->id)->ignore($project?->id)],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:3000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $mission = Mission::findOrFail($data['mission_id']);
        abort_unless($mission->organization_id === $organization->id, 422, 'La mission ne dépend pas de cette organisation.');
        return $data;
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
