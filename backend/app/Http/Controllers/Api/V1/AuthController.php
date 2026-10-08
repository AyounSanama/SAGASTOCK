<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\HealthFacility;
use App\Models\Mission;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\ApplicationNavigationService;
use App\Services\GovernanceService;
use App\Services\UserScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'login' => ['nullable', 'string', 'max:190'],
            'email' => ['nullable', 'string', 'max:190'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
            'device_id' => ['required', 'uuid'],
            'platform' => ['required', 'in:android,ios'],
        ]);
        $login = $data['login'] ?? $data['email'] ?? null;
        if (! $login) {
            throw ValidationException::withMessages(['login' => ['Adresse e-mail ou identifiant requis.']]);
        }
        $user = User::where('email', $login)->orWhere('username', strtolower($login))->first();
        if ($user?->locked_until?->isFuture()) {
            throw ValidationException::withMessages(['login' => ['Compte temporairement verrouillé. Réessayez plus tard.']]);
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
            throw ValidationException::withMessages(['login' => ['Identifiants incorrects.']]);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['login' => ['Ce compte est désactivé.']]);
        }
        if ($user->organization_id && ! $user->organization()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['login' => ['L’organisation rattachée à ce compte est désactivée.']]);
        }
        if ($reason = \App\Http\Middleware\EnsureAccountIsActive::blockingReason($user)) {
            throw ValidationException::withMessages(['login' => [$reason]]);
        }
        $knownDevice = Device::where('fingerprint', $data['device_id'])->first();
        if ($knownDevice?->revoked_at) {
            throw ValidationException::withMessages(['device_id' => ['Cet appareil a été révoqué. Contactez un administrateur.']]);
        }
        // Un appareil ne doit jamais conserver simultanément les jetons de
        // deux identités. Le nom du jeton est l'empreinte stable du terminal.
        PersonalAccessToken::where('name', $data['device_id'])->delete();
        Device::where('fingerprint', $data['device_id'])
            ->where('user_id', '!=', $user->id)
            ->delete();
        Device::updateOrCreate(
            ['fingerprint' => $data['device_id']],
            ['id' => $data['device_id'], 'user_id' => $user->id, 'name' => $data['device_name'], 'platform' => $data['platform'], 'last_seen_at' => now(), 'revoked_at' => null],
        );
        $user->update(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null]);
        $payload = $this->userPayload($user);

        return response()->json([
            'token' => $user->createToken(
                $data['device_id'],
                ['*'],
                now()->addDays(\App\Http\Middleware\ExtendMobileTokenLifetime::LIFETIME_DAYS),
            )->plainTextToken,
            'user' => $payload,
            'role' => strtoupper((string) $payload['role']),
            'permissions' => $payload['permissions'],
            'scope' => $payload['access_scope'],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    public function updateLocale(Request $request): JsonResponse
    {
        $supported = config('pharmacare_languages.translated_locales', ['fr']);
        $data = $request->validate([
            'preferred_locale' => ['required', 'string', Rule::in($supported)],
        ]);
        $request->user()->update($data);

        return response()->json(['user' => $this->userPayload($request->user()->refresh())]);
    }

    /** Niveau 4 — Mode d'affichage (Clair / Sombre / Système), partagé avec le Web. */
    public function updateTheme(Request $request): JsonResponse
    {
        $data = $request->validate(['theme_preference' => ['required', Rule::in(['light', 'dark', 'system'])]]);
        $request->user()->update($data);

        return response()->json(['user' => $this->userPayload($request->user()->refresh())]);
    }

    /** Mon profil (mobile) : prénom, nom, identifiant, e-mail, téléphone ; règles du Web. */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = \App\Http\Controllers\Web\ProfileController::applyUpdate($request, app(\App\Services\AuditService::class));

        return response()->json(['user' => $this->userPayload($user->refresh())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function userPayload(User $user): array
    {
        $governance = app(GovernanceService::class);
        $navigation = app(ApplicationNavigationService::class);
        $scopes = app(UserScopeService::class);
        $organization = $user->organization()
            ->with(['countries' => fn ($query) => $query
                ->where('countries.is_active', true)
                ->orderBy('countries.name')])
            ->first();
        $site = Site::with(['healthFacility.projects', 'healthFacility.mission.country'])
            ->whereIn('id', $scopes->directSiteIds($user))
            ->first();
        $facility = $site?->healthFacility;
        $mission = Mission::with('country')
            ->withCount('projects')
            ->whereIn('id', $scopes->coordinationMissionIds($user))
            ->first();
        $project = Project::with([
            'organization:id,code,name',
            'mission.country:id,iso2,name',
            'donors:id,code,name',
            'programs:id,code,name',
        ])->whereIn('id', $scopes->directProjectIds($user))->first();
        $project ??= $facility?->projects()->with([
            'organization:id,code,name', 'mission.country:id,iso2,name',
            'donors:id,code,name', 'programs:id,code,name',
        ])->first();
        $mission ??= $project?->mission()->with('country')->withCount('projects')->first()
            ?? $facility?->mission()->with('country')->withCount('projects')->first();

        return [
            'id' => $user->uuid, 'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'preferred_locale' => $user->preferred_locale ?: 'fr',
            'theme_preference' => $user->theme_preference ?: 'light',
            'organization_id' => $organization?->id,
            'organization' => $organization ? [
                'id' => $organization->id,
                'code' => $organization->code,
                'name' => $organization->name,
                'is_active' => $organization->is_active,
                'geographic_access_type' => $organization->geographic_access_type,
                'countries' => $organization->countries->map(fn ($country) => [
                    'id' => $country->id,
                    'iso2' => $country->iso2,
                    'name' => $country->name,
                ])->values()->all(),
            ] : null,
            'mission_id' => $mission?->id,
            'country_id' => $mission?->country_id,
            'coordination' => $mission ? [
                'id' => $mission->id,
                'code' => $mission->code,
                'name' => $mission->name,
                'is_active' => $mission->is_active,
                'starts_on' => $mission->starts_on?->toDateString(),
                'ends_on' => $mission->ends_on?->toDateString(),
                'country' => $mission->country ? [
                    'id' => $mission->country->id,
                    'iso2' => $mission->country->iso2,
                    'name' => $mission->country->name,
                ] : null,
                'projects_count' => $mission->projects_count,
                'health_facilities_count' => HealthFacility::where('mission_id', $mission->id)->count(),
            ] : null,
            'project_id' => $project?->id,
            'project' => $project,
            'facility_id' => $facility?->id,
            'facility' => $facility?->only(['id', 'organization_id', 'mission_id', 'code', 'name', 'facility_type', 'care_level', 'locality', 'is_active']),
            'site_id' => $site?->id,
            'site' => $site?->only(['id', 'organization_id', 'health_facility_id', 'code', 'name', 'site_type', 'location', 'is_active']),
            'roles' => $user->roles()->pluck('code')->all(),
            'role' => $governance->roleCode($user),
            'dashboard' => $governance->dashboard($user),
            'permissions' => $navigation->permissions($user)->all(),
            'scopes' => $user->roles()->get()->map(fn ($role) => [
                'role' => $governance->canonicalCode($role->code),
                'type' => $role->pivot->scope_type,
                'id' => $role->pivot->scope_id,
            ])->values()->all(),
            'access_scope' => $navigation->scope($user),
            'navigation' => $navigation->mobileItems($user),
            'must_change_password' => $user->must_change_password,
            'read_only' => (bool) $user->read_only,
        ];
    }
}
