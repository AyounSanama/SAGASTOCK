<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function index(Request $request): View
    {
        $this->allow('roles.manage');
        $logs = AuditLog::with('user:id,name,email')
            ->when($request->string('event')->toString(), fn ($query, $event) => $query->where('event', 'like', "{$event}%"))
            ->latest()->paginate(30);
        return view('security.index', [
            'roles' => $this->scopes->roles($request->user())->with('permissions')->orderBy('name')->get(),
            'permissions' => Permission::orderBy('name')->get(),
            'organizations' => $this->scopes->organizations($request->user())->orderBy('name')->get(['id', 'name']),
            'projects' => $this->scopes->projects($request->user())->orderBy('name')->get(['id', 'name']),
            'facilities' => $this->scopes->facilities($request->user())->orderBy('name')->get(['id', 'name']),
            'sites' => $this->scopes->sites($request->user())->orderBy('name')->get(['id', 'name']),
            'logs' => $logs,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->allow('roles.manage');
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', 'unique:roles,code'],
            'name' => ['required', 'string', 'max:120'], 'scope' => ['required', 'string'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        [$scopeType, $scopeId] = $this->parseScope($data['scope']);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        $role = Role::create(['code' => $data['code'], 'name' => $data['name'], 'is_system' => false, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $this->audit->record($request, 'role.created', $role, [], $role->only(['code', 'name', 'scope_type', 'scope_id']));
        return back()->with('status', 'Rôle créé.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->allow('roles.manage');
        abort_unless($this->scopes->canManageRole($request->user(), $role), 403, 'Les rôles système sont protégés.');
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', Rule::unique('roles')->ignore($role->id)],
            'name' => ['required', 'string', 'max:120'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $old = $role->only(['code', 'name']);
        $role->update(['code' => $data['code'], 'name' => $data['name']]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $this->audit->record($request, 'role.updated', $role, $old, $role->only(['code', 'name']));
        return back()->with('status', 'Rôle mis à jour.');
    }

    public function destroy(Request $request, Role $role): RedirectResponse
    {
        $this->allow('roles.manage');
        abort_unless($this->scopes->canManageRole($request->user(), $role), 403);
        if ($role->users()->exists()) return back()->withErrors(['role' => 'Ce rôle est encore attribué à des utilisateurs.']);
        $this->audit->record($request, 'role.deleted', $role, $role->only(['code', 'name', 'scope_type', 'scope_id']));
        $role->delete();
        return back()->with('status', 'Rôle supprimé.');
    }

    private function parseScope(string $scope): array
    {
        if ($scope === 'platform') return ['platform', null];
        [$type, $id] = array_pad(explode(':', $scope, 2), 2, null);
        abort_unless($id && in_array($type, ['organization', 'project', 'facility', 'site'], true), 422, 'Périmètre invalide.');
        return [$type, $id];
    }

    private function allow(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }
}
