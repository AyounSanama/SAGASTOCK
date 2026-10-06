<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private GovernanceService $governance,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route($this->governance->landingRoute(Auth::user()));
        }

        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->clearAuthenticatedSession($request);

        $data = $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required']]);
        $user = User::where('email', $data['login'])->orWhere('username', strtolower($data['login']))->first();
        if ($user?->locked_until?->isFuture()) {
            return back()->withErrors(['login' => 'Compte temporairement verrouillé. Réessayez plus tard.'])->onlyInput('login');
        }
        if ($user?->locked_until?->isPast()) {
            $user->update(['failed_login_attempts' => 0, 'locked_until' => null]);
            $user->refresh();
        }
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->update(['failed_login_attempts' => $attempts, 'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null]);
            }

            return back()->withErrors(['login' => 'Identifiants incorrects.'])->onlyInput('login');
        }
        if (! $user->is_active) {
            return back()->withErrors(['login' => 'Ce compte est désactivé.'])->onlyInput('login');
        }
        if ($user->organization_id && ! $user->organization()->where('is_active', true)->exists()) {
            return back()->withErrors(['login' => 'L’organisation rattachée à ce compte est désactivée.'])->onlyInput('login');
        }
        if ($reason = \App\Http\Middleware\EnsureAccountIsActive::blockingReason($user)) {
            return back()->withErrors(['login' => $reason])->onlyInput('login');
        }
        Auth::login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null]);
        $request->session()->regenerate();

        if (Auth::user()->must_change_password) {
            return redirect()->route('profile.show')
                ->with('status', 'Vous devez remplacer le mot de passe temporaire.');
        }

        // Ne pas réutiliser une ancienne URL « intended » (par exemple le
        // profil du compte précédent) pour l'Admin Coordination.
        if ($this->governance->roleCode(Auth::user()) === GovernanceService::COORDINATION_ADMIN) {
            return redirect()->route($this->governance->landingRoute(Auth::user()));
        }

        return redirect()->intended(route($this->governance->landingRoute(Auth::user())));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->clearAuthenticatedSession($request);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function dashboard(Request $request): View
    {
        $this->authorizeUsers('users.view');

        return view('dashboard.index', [
            'users' => $this->scopes->users(Auth::user(), User::with('roles'))
                ->when($request->string('search')->toString(), fn ($query, $search) => $query
                    ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
                ->when($request->filled('role_id'), fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->whereKey($request->integer('role_id'))))
                ->when($request->get('status') === 'active', fn ($query) => $query->where('is_active', true))
                ->when($request->get('status') === 'inactive', fn ($query) => $query->where('is_active', false))
                ->orderBy('name')->paginate(20)->withQueryString(),
            'roles' => $this->scopes->roles(Auth::user())->orderBy('name')->get(),
            'organizations' => $this->scopes->organizations(Auth::user())->orderBy('name')->get(['id', 'name']),
            'projects' => $this->scopes->projects(Auth::user())->with('organization:id,name')->orderBy('name')->get(['id', 'organization_id', 'name']),
        ]);
    }

    public function createUser(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()->hasPermission('users.manage')
                || $request->user()->hasPermission('users.create_site_admin'),
            403,
        );
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'alpha_dash', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope' => ['nullable', 'string', 'max:100'],
            'organization_id' => ['nullable', 'uuid', 'exists:organizations,id'],
            'mission_id' => ['nullable', 'uuid', 'exists:missions,id'],
            'project_id' => ['nullable', 'uuid', 'exists:projects,id'],
            'health_facility_id' => ['nullable', 'uuid', 'exists:health_facilities,id'],
            'dispensing_site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'password' => ['nullable', 'confirmed', PasswordPolicy::rule()],
            'must_change_password' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
            'form_context' => ['nullable', 'string', 'max:80'],
        ]);
        $role = $this->scopes->assignableRoles($request->user())->findOrFail($data['role_id']);
        $roleCode = $this->governance->canonicalCode($role->code);
        $organization = null;
        if ($roleCode === GovernanceService::COORDINATION_ADMIN) {
            $request->validate(['organization_id' => ['required'], 'mission_id' => ['required']]);
            $organization = $this->scopes->organizations($request->user())
                ->findOrFail($data['organization_id']);
            $mission = Mission::whereKey($data['mission_id'])->where('organization_id', $organization->id)->firstOrFail();
            [$scopeType, $scopeId] = ['mission', $mission->id];
        } elseif (in_array($roleCode, [GovernanceService::PROJECT_ADMIN, GovernanceService::SITE_ADMIN], true)
            || ($roleCode === GovernanceService::SITE_USER && $this->governance->roleCode($request->user()) === GovernanceService::PROJECT_ADMIN)) {
            $facilityRole = $roleCode !== GovernanceService::PROJECT_ADMIN;
            $request->validate([
                'organization_id' => ['required'], 'mission_id' => ['required'], 'project_id' => ['required'],
                'health_facility_id' => [Rule::requiredIf($facilityRole)],
            ]);
            $organization = $this->scopes->organizations($request->user())->findOrFail($data['organization_id']);
            $mission = Mission::whereKey($data['mission_id'])->where('organization_id', $organization->id)->firstOrFail();
            $project = $this->scopes->projects($request->user())->whereKey($data['project_id'])
                ->where('organization_id', $organization->id)->where('mission_id', $mission->id)->firstOrFail();
            if ($roleCode === GovernanceService::PROJECT_ADMIN) {
                [$scopeType, $scopeId] = ['project', $project->id];
            } else {
                $facility = $this->scopes->facilities($request->user())->whereKey($data['health_facility_id'])
                    ->whereHas('projects', fn ($query) => $query->whereKey($project->id))->firstOrFail();
                // DEC-05 : site principal invisible quand aucun site n'est choisi.
                $site = empty($data['dispensing_site_id'])
                    ? app(\App\Services\HealthFacilityConfigurationService::class)->ensurePrimarySite($facility)
                    : $this->scopes->sites($request->user())->whereKey($data['dispensing_site_id'])->where('health_facility_id', $facility->id)->firstOrFail();
                $this->governance->assertFacilityOpenForAccounts($site->id);
                [$scopeType, $scopeId] = ['site', $site->id];
            }
        } else {
            $request->validate(['scope' => ['required', 'string', 'max:100']]);
            [$scopeType, $scopeId] = $this->parseScope($data['scope']);
            abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        }
        abort_unless($this->governance->canAssign($request->user(), $role, $scopeType, $scopeId), 403);
        $generated = empty($data['password']);
        $password = $generated ? Str::password(16, symbols: true) : $data['password'];
        $user = User::create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'username' => strtolower($data['username']),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'organization_id' => $organization?->id,
            'password' => $password,
            'is_active' => $request->boolean('is_active', true),
            'must_change_password' => $generated || $request->boolean('must_change_password'),
            'read_only' => $this->governance->createsReadOnlyAccount($request->user(), $role),
        ]);
        $user->roles()->sync([$data['role_id'] => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]);
        if ($this->governance->roleCode($request->user()) === GovernanceService::SITE_ADMIN) {
            $allowed = Permission::whereIn('code', GovernanceService::SITE_DELEGABLE_PERMISSIONS)
                ->whereIn('id', $data['permission_ids'] ?? [])->pluck('id');
            abort_unless($allowed->count() === count(array_unique($data['permission_ids'] ?? [])), 403);
            $user->directPermissions()->sync($allowed);
        } else {
            abort_unless(empty($data['permission_ids']), 403);
        }
        $this->audit->record($request, 'user.created', $user, [], [
            'name' => $user->name,
            'email' => $user->email,
            'role_id' => $data['role_id'],
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);
        $response = ($data['form_context'] ?? null) === 'mission-project-admin' && isset($mission)
            ? redirect()->route('organizations.missions.show', [$organization, $mission])
                ->with('status', "L’administrateur projet {$user->name} a été créé et affecté avec succès.")
            : redirect()->route('users.index')->with('success', "L’utilisateur {$user->name} a été créé avec succès.");

        return $response;
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUsers('users.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope' => ['required', 'string', 'max:100'],
        ]);
        [$scopeType, $scopeId] = $this->parseScope($data['scope']);
        Gate::authorize('update', $user);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        $role = $this->scopes->assignableRoles($request->user())->findOrFail($data['role_id']);
        abort_unless($this->governance->canAssign($request->user(), $role, $scopeType, $scopeId), 403);
        $old = $user->only(['name', 'email', 'phone', 'is_active']);
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->roles()->sync([$data['role_id'] => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]);
        $this->audit->record($request, 'user.updated', $user, $old, [
            ...$user->only(['name', 'email', 'phone', 'is_active']),
            'role_id' => $data['role_id'],
            'scope_type' => $scopeType,
            'scope_id' => $scopeId,
        ]);

        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUsers('users.manage');
        Gate::authorize('resetPassword', $user);
        $temporary = Str::password(16, symbols: true);
        $user->update(['password' => $temporary, 'must_change_password' => true]);
        $user->tokens()->delete();
        $this->audit->record($request, 'user.password_reset', $user);

        return back()->with('success', 'Mot de passe réinitialisé. L’utilisateur devra le modifier lors de sa prochaine connexion.');
    }

    private function parseScope(string $scope): array
    {
        if ($scope === 'platform') {
            return ['platform', null];
        }
        [$type, $id] = array_pad(explode(':', $scope, 2), 2, null);
        abort_unless($id && in_array($type, ['organization', 'mission', 'project', 'facility', 'site'], true), 422, 'Périmètre invalide.');
        $exists = match ($type) {
            'organization' => Organization::whereKey($id)->exists(),
            'mission' => Mission::whereKey($id)->exists(),
            'project' => Project::whereKey($id)->exists(),
            'facility' => HealthFacility::whereKey($id)->exists(),
            'site' => Site::whereKey($id)->exists(),
        };
        abort_unless($exists, 422, 'Périmètre introuvable.');

        return [$type, $id];
    }

    private function authorizeUsers(string $permission): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->hasPermission($permission), 403);
    }

    private function clearAuthenticatedSession(Request $request): void
    {
        $keys = [
            'access_token',
            'refresh_token',
            'currentUser',
            'role',
            'permissions',
            'scope',
            'organizationId',
            'missionId',
            'projectId',
            'siteId',
            'visibleModules',
            'lastRoute',
            'dashboardState',
            'configurationState',
            'workflowSteps',
            'formCache',
            'listCache',
            'activeProvider',
            'activeBlock',
            'temporarySession',
        ];

        foreach ($keys as $key) {
            $request->session()->forget($key);
        }

        $request->session()->forget('_previous');
        $request->session()->forget('_flash');
    }
}
