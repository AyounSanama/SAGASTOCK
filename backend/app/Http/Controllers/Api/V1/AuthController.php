<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\GovernanceService;
use App\Services\ApplicationNavigationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Illuminate\Validation\ValidationException;

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
        if (! $login) throw ValidationException::withMessages(['login' => ['Adresse e-mail ou identifiant requis.']]);
        $user = User::where('email', $login)->orWhere('username', strtolower($login))->first();
        if ($user?->locked_until?->isFuture()) {
            throw ValidationException::withMessages(['login' => ['Compte temporairement verrouillé. Réessayez plus tard.']]);
        }
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            if ($user) {
                $attempts = $user->failed_login_attempts + 1;
                $user->update(['failed_login_attempts' => $attempts, 'locked_until' => $attempts >= 5 ? now()->addMinutes(15) : null]);
            }
            throw ValidationException::withMessages(['login' => ['Identifiants incorrects.']]);
        }
        if (! $user->is_active) throw ValidationException::withMessages(['login' => ['Ce compte est désactivé.']]);
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
            'token' => $user->createToken($data['device_id'])->plainTextToken,
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

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    private function userPayload(User $user): array
    {
        $governance = app(GovernanceService::class);
        $navigation = app(ApplicationNavigationService::class);
        return [
            'id' => $user->uuid, 'name' => $user->name, 'username' => $user->username,
            'email' => $user->email, 'roles' => $user->roles()->pluck('code')->all(),
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
        ];
    }
}
