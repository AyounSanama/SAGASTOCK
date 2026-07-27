<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(Request $request): JsonResponse
    {
        $organizations = Organization::query()
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%")))
            ->when($request->has('active'), fn ($query) => $query->where('is_active', $request->boolean('active')))
            ->orderBy('name')->paginate(20);

        return response()->json($organizations);
    }

    public function show(Organization $organization): JsonResponse
    {
        return response()->json(['organization' => $organization]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = Organization::create($this->validated($request));
        $this->audit->record($request, 'organization.created', $organization, [], $organization->only(['code', 'name', 'country_code', 'is_active']));
        return response()->json(['organization' => $organization], 201);
    }

    public function update(Request $request, Organization $organization): JsonResponse
    {
        $old = $organization->only(['code', 'name', 'legal_name', 'email', 'phone', 'country_code', 'address', 'is_active']);
        $organization->update($this->validated($request, $organization));
        $this->audit->record($request, 'organization.updated', $organization, $old, $organization->only(array_keys($old)));
        return response()->json(['organization' => $organization]);
    }

    public function destroy(Request $request, Organization $organization): JsonResponse
    {
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
}
