<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mission;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MissionController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $missions = $organization->missions()->with('country:id,iso2,name')
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);
        return response()->json($missions);
    }

    public function store(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $mission = $organization->missions()->create($this->validated($request, $organization));
        $this->audit->record($request, 'mission.created', $mission, [], $mission->only([
            'organization_id', 'country_id', 'code', 'name', 'starts_on',
            'ends_on', 'address', 'manager_name', 'phone', 'email',
            'description', 'is_active',
        ]));
        return response()->json(['mission' => $mission->load('country:id,iso2,name')], 201);
    }

    public function update(Request $request, Organization $organization, Mission $mission): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($mission->organization_id === $organization->id, 404);
        $old = $mission->only([
            'country_id', 'code', 'name', 'starts_on', 'ends_on', 'address',
            'manager_name', 'phone', 'email', 'description', 'is_active',
        ]);
        $mission->update($this->validated($request, $organization, $mission));
        $this->audit->record($request, 'mission.updated', $mission, $old, $mission->only(array_keys($old)));
        return response()->json(['mission' => $mission->load('country:id,iso2,name')]);
    }

    public function destroy(Request $request, Organization $organization, Mission $mission): JsonResponse
    {
        $this->accessible($request, $organization);
        abort_unless($mission->organization_id === $organization->id, 404);
        $mission->delete();
        $this->audit->record($request, 'mission.archived', $mission);
        return response()->json(status: 204);
    }

    public function restore(Request $request, Organization $organization, string $mission): JsonResponse
    {
        $this->accessible($request, $organization);
        $model = $organization->missions()->onlyTrashed()->findOrFail($mission);
        $model->restore(); $model->update(['is_active' => true]);
        $this->audit->record($request, 'mission.restored', $model);
        return response()->json(['mission' => $model->load('country')]);
    }

    private function validated(Request $request, Organization $organization, ?Mission $mission = null): array
    {
        return $request->validate([
            'country_id' => ['required', 'uuid', 'exists:countries,id'],
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('missions')->where('organization_id', $organization->id)->ignore($mission?->id)],
            'name' => ['required', 'string', 'max:160'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'address' => ['nullable', 'string', 'max:1000'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'description' => ['nullable', 'string', 'max:3000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
