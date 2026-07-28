<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request): JsonResponse
    {
        $organizations = $this->scopes->organizations($request->user())
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(20);

        return response()->json($organizations);
    }

    public function show(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        return response()->json(['organization' => $organization]);
    }

    public function archived(Request $request): JsonResponse
    {
        return response()->json($this->scopes->archivedOrganizations($request->user())->latest('deleted_at')->paginate(20));
    }

    public function restore(Request $request, string $organization): JsonResponse
    {
        $model = $this->scopes->archivedOrganizations($request->user())->findOrFail($organization);
        $model->restore();
        $model->update(['is_active' => true]);
        $this->audit->record($request, 'organization.restored', $model);
        return response()->json(['organization' => $model]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = Organization::create($this->validated($request));
        $this->audit->record($request, 'organization.created', $organization, [], $organization->only(['code', 'name', 'country_code', 'is_active']));
        return response()->json(['organization' => $organization], 201);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));
        return response()->json(['organization' => $organization]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
        $this->accessible($request, $organization);
        $organization->update(['is_active' => false]);
        $organization->delete();
        $this->audit->record($request, 'organization.archived', $organization);
        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:40', Rule::unique('organizations')->ignore($organization?->id)],
            'name' => ['required', 'string', 'max:160'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'country_code' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        if (isset($data['country_code'])) $data['country_code'] = strtoupper($data['country_code']);
        return $data;
    }

    private function accessible(Request $request, Organization $organization): void
    {
        abort_unless($this->scopes->organizations($request->user())->whereKey($organization->id)->exists(), 404);
    }
}
