<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use App\Support\PasswordPolicy;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function show(Request $request): View
    {
        return view('profile.show', [
            'user' => $request->user()->load('roles'),
            'supportedLanguages' => collect(config('pharmacare_languages.translated_locales', ['fr']))
                ->mapWithKeys(fn (string $locale) => [$locale => config("pharmacare_languages.catalog.$locale", $locale)]),
            'devices' => $request->user()->devices()->latest('last_seen_at')->get(),
            'sessions' => DB::table('sessions')->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get(),
            'currentSessionId' => $request->session()->getId(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'username' => ['required', 'alpha_dash', 'max:80', Rule::unique('users')->ignore($user->id)],
            'email' => ['required', 'email', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'preferred_locale' => ['sometimes', 'required', Rule::in(config('pharmacare_languages.translated_locales', ['fr']))],
        ]);
        $old = $user->only(['first_name', 'last_name', 'username', 'email', 'phone', 'preferred_locale']);
        $user->update([...$data, 'username' => strtolower($data['username']), 'name' => trim($data['first_name'].' '.$data['last_name'])]);
        $this->audit->record($request, 'profile.updated', $user, $old, $user->only(array_keys($old)));
        return back()->with('status', 'Profil mis à jour.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', PasswordPolicy::rule()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password']), 'must_change_password' => false, 'password_changed_at' => now()]);
        $request->user()->tokens()->delete();
        $this->audit->record($request, 'user.password_changed', $request->user());
        return back()->with('status', 'Mot de passe modifié. Les sessions mobiles ont été révoquées.');
    }

    public function revoke(Request $request, Device $device): RedirectResponse
    {
        abort_unless($device->user_id === $request->user()->id, 404);
        $device->update(['revoked_at' => now()]);
        $request->user()->tokens()->where('name', $device->id)->delete();
        $this->audit->record($request, 'device.revoked', $device);
        return back()->with('status', 'Appareil révoqué.');
    }

    public function revokeSession(Request $request, string $session): RedirectResponse
    {
        abort_if($session === $request->session()->getId(), 422, 'La session courante ne peut pas être révoquée ici.');
        $deleted = DB::table('sessions')->where('id', $session)->where('user_id', $request->user()->id)->delete();
        abort_unless($deleted, 404);
        $this->audit->record($request, 'session.revoked', $request->user(), [], ['session_id' => $session]);
        return back()->with('status', 'Session Web révoquée.');
    }
}
