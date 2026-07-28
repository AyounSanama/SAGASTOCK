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
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(private AuditService $audit, private UserScopeService $scopes) {}

    public function create(): View { return view('auth.login'); }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required']]);
        $user = User::where('email', $data['login'])->orWhere('username', strtolower($data['login']))->first();
        if ($user?->locked_until?->isFuture()) {
            return back()->withErrors(['login' => 'Compte temporairement verrouillé. Réessayez plus tard.'])->onlyInput('login');
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
        Auth::login($user, $request->boolean('remember'));
        $user->update(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null]);
        $request->session()->regenerate();
        return Auth::user()->must_change_password
            ? redirect()->route('profile.show')->with('status', 'Vous devez remplacer le mot de passe temporaire.')
            : redirect()->intended('/dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
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
        $this->authorizeUsers('users.manage');
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'alpha_dash', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'scope' => ['required', 'string', 'max:100'],
            'password' => ['nullable', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['nullable', 'boolean'],
        ]);
        [$scopeType, $scopeId] = $this->parseScope($data['scope']);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        abort_unless($this->scopes->assignableRoles($request->user())->whereKey($data['role_id'])->exists(), 403);
        $generated = empty($data['password']);
        $password = $generated ? Str::password(16, symbols: true) : $data['password'];
        $user = User::create([
            'name' => trim($data['first_name'].' '.$data['last_name']),
            'first_name' => $data['first_name'], 'last_name' => $data['last_name'],
            'username' => strtolower($data['username']),
            'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'password' => $password, 'is_active' => true,
            'must_change_password' => $generated || $request->boolean('must_change_password'),
        ]);
        $user->roles()->sync([$data['role_id'] => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]);
        $this->audit->record($request, 'user.created', $user, [], [
            'name' => $user->name, 'email' => $user->email, 'role_id' => $data['role_id'],
            'scope_type' => $scopeType, 'scope_id' => $scopeId,
        ]);
        $response = redirect()->route('dashboard')->with('success', "L’utilisateur {$user->name} a été créé avec succès.");
        return $generated ? $response->with('temporary_password', $password) : $response;
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
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        abort_unless($this->scopes->assignableRoles($request->user())->whereKey($data['role_id'])->exists(), 403);
        $old = $user->only(['name', 'email', 'phone', 'is_active']);
        $user->update([
            'name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $user->roles()->sync([$data['role_id'] => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]);
        $this->audit->record($request, 'user.updated', $user, $old, [
            ...$user->only(['name', 'email', 'phone', 'is_active']),
            'role_id' => $data['role_id'], 'scope_type' => $scopeType, 'scope_id' => $scopeId,
        ]);
        return back()->with('success', 'Utilisateur mis à jour.');
    }

    public function resetUserPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeUsers('users.manage');
        abort_unless($this->scopes->canAccess(request()->user(), $user), 404);
        $temporary = Str::password(16, symbols: true);
        $user->update(['password' => $temporary, 'must_change_password' => true]);
        $user->tokens()->delete();
        $this->audit->record($request, 'user.password_reset', $user);
        return back()->with('success', 'Mot de passe réinitialisé.')->with('temporary_password', $temporary);
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

    private function authorizeUsers(string $permission): void
    {
        abort_unless(Auth::user()?->hasPermission($permission), 403);
    }
}
