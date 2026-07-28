<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Models\HealthFacility;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function create(): View
    {
        $this->allow('users.manage');
        return view('users.create', [
            'roles' => $this->scopes->assignableRoles(Auth::user())->orderBy('name')->get(),
            'organizations' => $this->scopes->organizations(Auth::user())->orderBy('name')->get(['id', 'name']),
            'projects' => $this->scopes->projects(Auth::user())->with('organization:id,name')->orderBy('name')->get(['id', 'organization_id', 'name']),
            'facilities' => $this->scopes->facilities(Auth::user())->with('organization:id,name')->orderBy('name')->get(['id', 'organization_id', 'name']),
            'sites' => $this->scopes->sites(Auth::user())->with('healthFacility:id,name')->orderBy('name')->get(['id', 'health_facility_id', 'name']),
        ]);
    }

    public function archived(Request $request): View
    {
        $this->allow('users.view');
        $users = $this->scopes->users(Auth::user(), User::onlyTrashed()->with('roles'))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->latest('deleted_at')->paginate(20)->withQueryString();
        return view('users.archived', compact('users'));
    }

    public function showArchived(string $user): View
    {
        $this->allow('users.view');
        $managedUser = $this->scopes->users(Auth::user(), User::onlyTrashed()->with(['roles.permissions', 'devices']))->findOrFail($user);
        return view('users.archived-show', compact('managedUser'));
    }

    public function restore(Request $request, string $user): RedirectResponse
    {
        $this->allow('users.manage');
        $managedUser = $this->scopes->users($request->user(), User::onlyTrashed())->findOrFail($user);
        $archivedAt = $managedUser->deleted_at?->toISOString();
        $managedUser->restore();
        $managedUser->update(['is_active' => true]);
        $this->audit->record($request, 'user.restored', $managedUser, ['deleted_at' => $archivedAt], ['is_active' => true]);
        return redirect()->route('dashboard')->with('success', "L’utilisateur {$managedUser->name} a été restauré.");
    }

    public function show(User $user): View
    {
        $this->allow('users.view');
        abort_unless($this->scopes->canAccess(Auth::user(), $user), 404);
        return view('users.show', ['managedUser' => $user->load(['roles.permissions', 'devices'])]);
    }

    public function edit(User $user): View
    {
        $this->allow('users.manage');
        abort_unless($this->scopes->canAccess(Auth::user(), $user), 404);
        return view('users.edit', [
            'managedUser' => $user->load('roles'),
            'roles' => $this->scopes->assignableRoles(Auth::user())->orderBy('name')->get(),
            'organizations' => $this->scopes->organizations(Auth::user())->orderBy('name')->get(['id', 'name']),
            'projects' => $this->scopes->projects(Auth::user())->with('organization:id,name')->orderBy('name')->get(['id', 'organization_id', 'name']),
            'facilities' => $this->scopes->facilities(Auth::user())->with('organization:id,name')->orderBy('name')->get(['id', 'organization_id', 'name']),
            'sites' => $this->scopes->sites(Auth::user())->with('healthFacility:id,name')->orderBy('name')->get(['id', 'health_facility_id', 'name']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->allow('users.manage');
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope' => ['required', 'string', 'max:100'],
        ]);
        [$scopeType, $scopeId] = $this->parseScope($data['scope']);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        abort_unless($this->scopes->assignableRoles($request->user())->whereKey($data['role_id'])->exists(), 403);
        $old = $user->only(['name', 'email', 'phone', 'is_active']);
        $user->update([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->roles()->sync([$data['role_id'] => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]);
        if (! $user->is_active) $user->tokens()->delete();
        $this->audit->record($request, 'user.updated', $user, $old, [
            ...$user->only(['name', 'email', 'phone', 'is_active']),
            'role_id' => $data['role_id'], 'scope_type' => $scopeType, 'scope_id' => $scopeId,
        ]);
        return redirect()->route('users.show', $user)->with('success', 'Utilisateur mis à jour.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->allow('users.manage');
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        abort_if($request->user()->is($user), 422, 'Vous ne pouvez pas archiver votre propre compte.');
        if ($user->roles()->where('code', 'platform_owner')->exists()) {
            $otherOwners = User::where('is_active', true)->whereKeyNot($user->id)
                ->whereHas('roles', fn ($query) => $query->where('code', 'platform_owner'))->exists();
            abort_unless($otherOwners, 422, 'Le dernier propriétaire actif de la plateforme ne peut pas être archivé.');
        }
        $user->tokens()->delete();
        $user->devices()->update(['revoked_at' => now()]);
        $user->update(['is_active' => false]);
        $user->delete();
        $this->audit->record($request, 'user.archived', $user, ['email' => $user->email, 'is_active' => true], ['is_active' => false]);
        return redirect()->route('dashboard')->with('success', 'Utilisateur archivé de manière sécurisée.');
    }

    private function parseScope(string $scope): array
    {
        if ($scope === 'platform') return ['platform', null];
        [$type, $id] = array_pad(explode(':', $scope, 2), 2, null);
        abort_unless($id && in_array($type, ['organization', 'project', 'facility', 'site'], true), 422, 'Périmètre invalide.');
        $exists = match ($type) {
            'organization' => Organization::whereKey($id)->exists(),
            'project' => Project::whereKey($id)->exists(),
            'facility' => HealthFacility::whereKey($id)->exists(),
            'site' => Site::whereKey($id)->exists(),
        };
        abort_unless($exists, 422, 'Périmètre introuvable.');
        return [$type, $id];
    }

    private function allow(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }
}
