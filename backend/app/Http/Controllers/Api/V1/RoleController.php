<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'roles' => $this->scopes->roles($request->user())
                ->with('permissions:id,code,name')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'max:80', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'scope_type' => ['sometimes', Rule::in(['platform', 'organization', 'project', 'facility', 'site'])],
            'scope_id' => ['nullable', 'uuid'],
            'permission_ids' => ['array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $scopeType = $data['scope_type'] ?? 'platform';
        $scopeId = $scopeType === 'platform' ? null : ($data['scope_id'] ?? null);
        abort_unless(
            $this->scopes->allowsScope($request->user(), $scopeType, $scopeId),
            403,
        );

        $role = Role::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_active' => $data['is_active'] ?? true,
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $this->audit->record($request, 'role.created', $role, [], $role->only([
            'code', 'name', 'description', 'is_active', 'scope_type', 'scope_id',
        ]));

        return response()->json(['role' => $role->load('permissions:id,code,name')], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_unless(
            $this->scopes->canManageRole($request->user(), $role),
            403,
            'Les rôles système sont protégés.',
        );
        $data = $request->validate([
            'code' => ['sometimes', 'required', 'alpha_dash', 'max:80', Rule::unique('roles')->ignore($role->id)],
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $old = $role->only(['code', 'name', 'description', 'is_active']);
        $role->update(collect($data)->except('permission_ids')->all());
        if (array_key_exists('permission_ids', $data)) {
            $role->permissions()->sync($data['permission_ids']);
        }
        $this->audit->record($request, 'role.updated', $role, $old, $role->only(['code', 'name', 'description', 'is_active']));

        return response()->json(['role' => $role->load('permissions:id,code,name')]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        if ($role->is_system) {
            return response()->json(['message' => 'Un rôle système ne peut pas être supprimé.'], 422);
        }
        abort_unless($this->scopes->canManageRole($request->user(), $role), 403);
        if ($role->users()->exists()) {
            return response()->json(['message' => 'Ce rôle est encore attribué à des utilisateurs.'], 422);
        }
        $this->audit->record($request, 'role.deleted', $role, $role->only([
            'code', 'name', 'scope_type', 'scope_id',
        ]));
        $role->delete();

        return response()->json(status: 204);
    }
}
