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
        $permissions = Permission::orderBy('name')->get();
        $permissionCategories = [
            'catalog' => 'Gestion des médicaments',
            'stocks' => 'Gestion du stock',
            'transfers' => 'Transferts de médicaments',
            'receipts' => 'Réceptions pharmaceutiques',
            'dispensing' => 'Dispensation des médicaments',
            'patients' => 'Patients',
            'prescriptions' => 'Prescriptions médicales',
            'reports' => 'Rapports et statistiques',
            'users' => 'Utilisateurs',
            'roles' => 'Rôles et permissions',
            'audit' => 'Journal d’audit',
            'organizations' => 'Organisations',
            'structures' => 'Formations sanitaires',
            'modules' => 'Activation des modules',
            'missions' => 'Missions',
            'projects' => 'Projets',
            'funding' => 'Bailleurs et programmes',
        ];
        $logs = AuditLog::with('user:id,name,email')
            ->when($request->string('event')->toString(), fn ($query, $event) => $query->where('event', 'like', "{$event}%"))
            ->latest()->paginate(30);
        $roles = $this->scopes->roles($request->user())
            ->with('permissions')
            ->withCount('users')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
        $selectedRole = $roles->firstWhere('id', $request->integer('role')) ?? $roles->first();

        return view('security.index', [
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy(fn ($permission) => explode('.', $permission->code)[0])
                ->map(fn ($items, $prefix) => [
                    'key' => $prefix,
                    'name' => $permissionCategories[$prefix] ?? 'Administration',
                    'permissions' => $items,
                ])->values(),
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
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'scope' => ['required', 'string'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        [$scopeType, $scopeId] = $this->parseScope($data['scope']);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
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
        $this->audit->record($request, 'role.created', $role, [], $role->only(['code', 'name', 'description', 'is_active', 'scope_type', 'scope_id']));
        return redirect()->route('security.index', ['role' => $role->id])->with('status', 'Le rôle a été créé avec succès.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->allow('roles.manage');
        abort_unless($this->scopes->canManageRole($request->user(), $role), 403, 'Les rôles système sont protégés.');
        $data = $request->validate([
            'code' => ['required', 'alpha_dash', Rule::unique('roles')->ignore($role->id)],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'permission_ids' => ['array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $old = $role->only(['code', 'name', 'description', 'is_active']);
        $role->update([
            'code' => $data['code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? $role->is_active,
        ]);
        $role->permissions()->sync($data['permission_ids'] ?? []);
        $this->audit->record($request, 'role.updated', $role, $old, $role->only(['code', 'name', 'description', 'is_active']));
        return redirect()->route('security.index', ['role' => $role->id])->with('status', 'Les modifications du rôle ont été enregistrées.');
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
