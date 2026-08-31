<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Support\PasswordPolicy;
class PasswordResetController extends Controller {
    public function request(): View { return view('auth.forgot-password'); }
    public function email(Request $request): RedirectResponse {
        $data=$request->validate(['email'=>['required','email']]); Password::sendResetLink(['email'=>$data['email']]);
        return back()->with('status','Si un compte correspond à cette adresse, un lien a été envoyé.');
    }
    public function resetForm(Request $request,string $token): View { return view('auth.reset-password',['token'=>$token,'email'=>$request->string('email')->toString()]); }
    public function reset(Request $request): RedirectResponse {
        $data=$request->validate(['email'=>['required','email'],'token'=>['required'],'password'=>['required','confirmed',PasswordPolicy::rule()]]);
        $status=Password::reset($data,function(User $user,string $password){$user->forceFill(['password'=>Hash::make($password),'must_change_password'=>false,'password_changed_at'=>now()])->setRememberToken(Str::random(60));$user->save();$user->tokens()->delete();event(new PasswordReset($user));});
        return $status===Password::PASSWORD_RESET?redirect()->route('login')->with('status','Mot de passe réinitialisé. Vous pouvez vous connecter.'):back()->withErrors(['email'=>'Le lien est invalide ou a expiré.']);
    }
}
