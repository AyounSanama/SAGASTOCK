<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Services\AuditService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;

class UserController extends Controller
{
    public function __construct(
        private AuditService $audit,
        private UserScopeService $scopes,
        private GovernanceService $governance,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $users = $this->scopes->users($request->user(), User::with('roles:id,code,name'))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20);

        return response()->json($users);
    }

    public function show(User $user): JsonResponse
    {
        abort_unless($this->scopes->canAccess(request()->user(), $user), 404);

        return response()->json(['user' => $user->load(['roles.permissions', 'devices'])]);
    }

    public function archived(Request $request): JsonResponse
    {
        return response()->json($this->scopes->users($request->user(), User::onlyTrashed()->with('roles:id,code,name'))
            ->when($request->string('search')->toString(), fn ($query, $search) => $query
                ->where(fn ($nested) => $nested->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%")))
            ->latest('deleted_at')->paginate(20));
    }

    public function showArchived(string $user): JsonResponse
    {
        return response()->json(['user' => $this->scopes->users(request()->user(), User::onlyTrashed()->with(['roles.permissions', 'devices']))->findOrFail($user)]);
    }

    public function restore(Request $request, string $user): JsonResponse
    {
        $managedUser = $this->scopes->users($request->user(), User::onlyTrashed())->findOrFail($user);
        $archivedAt = $managedUser->deleted_at?->toISOString();
        $managedUser->restore();
        $managedUser->update(['is_active' => true]);
        $this->audit->record($request, 'user.restored', $managedUser, ['deleted_at' => $archivedAt], ['is_active' => true]);

        return response()->json(['user' => $managedUser->load('roles:id,code,name')]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->hasPermission('users.manage')
                || $request->user()->hasPermission('users.create_site_admin'),
            403,
        );
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'alpha_dash', 'max:80', 'unique:users,username'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role_id' => ['nullable', 'required_without:role_ids', 'integer', 'exists:roles,id'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'scope_type' => ['nullable', Rule::in(['platform', 'organization', 'mission', 'project', 'facility', 'site'])],
            'scope_id' => ['nullable', 'uuid'],
            'password' => ['nullable', 'confirmed', PasswordPolicy::rule()],
            'must_change_password' => ['nullable', 'boolean'],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $generated = empty($data['password']);
        $password = $generated ? Str::password(16, symbols: true) : $data['password'];
        [$scopeType, $scopeId] = $this->scope($data['scope_type'] ?? 'platform', $data['scope_id'] ?? null);
        abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
        $roleIds = isset($data['role_id']) ? [$data['role_id']] : ($data['role_ids'] ?? []);
        abort_unless($this->scopes->assignableRoles($request->user())->whereIn('id', $roleIds)->count() === count($roleIds), 403);
        abort_unless(collect($roleIds)->every(fn ($id) => $this->governance->canAssign(
            $request->user(),
            Role::findOrFail($id),
            $scopeType,
            $scopeId,
        )), 403);
        $user = User::create([
            'name' => $data['name'], 'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null, 'username' => $data['username'] ?? null,
            'email' => $data['email'], 'phone' => $data['phone'] ?? null,
            'organization_id' => $this->organizationIdForScope($scopeType, $scopeId),
            'password' => $password, 'is_active' => true,
            'must_change_password' => $generated || ($data['must_change_password'] ?? false),
            'read_only' => collect($roleIds)->contains(fn ($id) => $this->governance->createsReadOnlyAccount($request->user(), Role::findOrFail($id))),
        ]);
        $user->roles()->sync(collect($roleIds)->mapWithKeys(fn ($id) => [$id => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]));
        $this->syncDelegatedPermissions($request, $user, $data['permission_ids'] ?? []);
        $this->audit->record($request, 'user.created', $user, [], ['name' => $user->name, 'email' => $user->email, 'scope_type' => $scopeType, 'scope_id' => $scopeId]);
        return response()->json([
            'user' => $user->load('roles:id,code,name'),
            'message' => 'Compte créé avec succès. L’utilisateur devra modifier son mot de passe lors de sa première connexion.',
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        $this->authorizeSiteAccountManagement($request, $user, 'users.update_site_admin');
        $old = $user->only(['name', 'email', 'phone', 'is_active']);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'first_name' => ['nullable', 'string', 'max:80'],
            'last_name' => ['nullable', 'string', 'max:80'],
            'username' => ['nullable', 'alpha_dash', 'max:80', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'], 'is_active' => ['sometimes', 'boolean'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'], 'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'scope_type' => ['nullable', Rule::in(['platform', 'organization', 'mission', 'project', 'facility', 'site'])], 'scope_id' => ['nullable', 'uuid'],
            'permission_ids' => ['nullable', 'array'], 'permission_ids.*' => ['integer', 'exists:permissions,id'],
        ]);
        $user->update(collect($data)->only(['name', 'first_name', 'last_name', 'username', 'email', 'phone', 'is_active'])->all());
        if (array_key_exists('role_id', $data) || array_key_exists('role_ids', $data)) {
            [$scopeType, $scopeId] = $this->scope($data['scope_type'] ?? 'platform', $data['scope_id'] ?? null);
            abort_unless($this->scopes->allowsScope($request->user(), $scopeType, $scopeId), 403);
            $roleIds = isset($data['role_id']) ? [$data['role_id']] : ($data['role_ids'] ?? []);
            abort_unless($this->scopes->assignableRoles($request->user())->whereIn('id', $roleIds)->count() === count($roleIds), 403);
            abort_unless(collect($roleIds)->every(fn ($id) => $this->governance->canAssign(
                $request->user(),
                Role::findOrFail($id),
                $scopeType,
                $scopeId,
            )), 403);
            $user->roles()->sync(collect($roleIds)->mapWithKeys(fn ($id) => [$id => ['scope_type' => $scopeType, 'scope_id' => $scopeId]]));
            $user->update(['organization_id' => $this->organizationIdForScope($scopeType, $scopeId)]);
        }
        if (array_key_exists('permission_ids', $data)) {
            $this->syncDelegatedPermissions($request, $user, $data['permission_ids']);
        }
        $this->audit->record($request, 'user.updated', $user, $old, $user->only(['name', 'email', 'phone', 'is_active']));

        return response()->json(['user' => $user->load('roles:id,code,name')]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        $this->authorizeSiteAccountManagement($request, $user, 'users.update_site_admin');
        $temporary = Str::password(16, symbols: true);
        $user->update(['password' => Hash::make($temporary), 'must_change_password' => true]);
        $user->tokens()->delete();
        $this->audit->record($request, 'user.password_reset', $user);

        return response()->json([
            'message' => 'Mot de passe réinitialisé. L’utilisateur devra le modifier lors de sa prochaine connexion.',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_unless($this->scopes->canAccess($request->user(), $user), 404);
        $this->authorizeSiteAccountManagement($request, $user, 'users.suspend_site_admin');
        abort_if($request->user()->is($user), 422, 'Vous ne pouvez pas archiver votre propre compte.');
        if ($user->roles()->whereIn('code', ['sago_admin', 'owner', 'platform_owner'])->exists()) {
            $otherOwners = User::where('is_active', true)->whereKeyNot($user->id)
                ->whereHas('roles', fn ($query) => $query->whereIn('code', ['sago_admin', 'owner', 'platform_owner']))->exists();
            abort_unless($otherOwners, 422, 'Le dernier propriétaire actif ne peut pas être archivé.');
        }
        $user->tokens()->delete();
        $user->devices()->update(['revoked_at' => now()]);
        $user->update(['is_active' => false]);
        $user->delete();
        $this->audit->record($request, 'user.archived', $user);

        return response()->json(status: 204);
    }

    public function roles(): JsonResponse
    {
        return response()->json(['roles' => $this->scopes->assignableRoles(request()->user())->orderBy('name')->get(['id', 'code', 'name', 'scope_type', 'scope_id'])]);
    }

    public function assignableRoles(Request $request): JsonResponse
    {
        $roles = $this->scopes->assignableRoles($request->user())
            ->orderByRaw("case code when 'project_admin' then 1 when 'site_admin' then 2 else 99 end")
            ->get(['id', 'code', 'name', 'scope_type']);

        return response()->json(['roles' => $roles]);
    }

    private function scope(string $type, ?string $id): array
    {
        if ($type === 'platform') {
            return [$type, null];
        }
        abort_unless($id, 422, 'Le périmètre doit être renseigné.');
        $exists = match ($type) {
            'organization' => Organization::whereKey($id)->exists(),
            'mission' => Mission::whereKey($id)->exists(),
            'project' => Project::whereKey($id)->exists(),
            'facility' => HealthFacility::whereKey($id)->exists(),
            'site' => Site::whereKey($id)->exists(),
            default => false,
        };
        abort_unless($exists, 422, 'Périmètre introuvable.');

        return [$type, $id];
    }

    private function organizationIdForScope(string $type, ?string $id): ?string
    {
        return match ($type) {
            'organization' => $id,
            'mission' => Mission::whereKey($id)->value('organization_id'),
            'project' => Project::whereKey($id)->value('organization_id'),
            'facility' => HealthFacility::whereKey($id)->value('organization_id'),
            'site' => Site::whereKey($id)->value('organization_id'),
            default => null,
        };
    }

    private function syncDelegatedPermissions(Request $request, User $user, array $permissionIds): void
    {
        $actorRole = $this->governance->roleCode($request->user());
        if ($actorRole !== GovernanceService::SITE_ADMIN) {
            abort_unless($permissionIds === [], 403);
            $user->directPermissions()->sync([]);

            return;
        }
        $targetRole = $this->governance->roleCode($user);
        abort_unless($targetRole === GovernanceService::SITE_USER, 403);
        $allowed = Permission::whereIn(
            'code',
            GovernanceService::SITE_DELEGABLE_PERMISSIONS,
        )->whereIn('id', $permissionIds)->pluck('id');
        abort_unless($allowed->count() === count(array_unique($permissionIds)), 403);
        $user->directPermissions()->sync($allowed);
    }

    private function authorizeSiteAccountManagement(Request $request, User $target, string $permission): void
    {
        $actor = $request->user();
        if ($actor->hasPermission('users.manage')) {
            return;
        }

        abort_unless($actor->hasPermission($permission), 403);
        abort_unless($this->governance->roleCode($target) === GovernanceService::SITE_ADMIN, 403);
        $targetSiteIds = $target->roles()
            ->wherePivot('scope_type', 'site')
            ->pluck('role_user.scope_id');
        abort_unless(
            $targetSiteIds->isNotEmpty()
                && $targetSiteIds->every(fn (string $id) => $this->scopes->siteIds($actor)->contains($id)),
            403,
        );
    }
}
