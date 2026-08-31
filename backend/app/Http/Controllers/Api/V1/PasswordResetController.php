<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use App\Support\PasswordPolicy;
class PasswordResetController extends Controller {
    public function forgot(Request $request): JsonResponse {
        $data=$request->validate(['email'=>['required','email']]);
        Password::sendResetLink(['email'=>$data['email']]);
        return response()->json(['message'=>'Si un compte correspond à cette adresse, un lien de réinitialisation a été envoyé.']);
    }
    public function reset(Request $request): JsonResponse {
        $data=$request->validate(['email'=>['required','email'],'token'=>['required','string'],'password'=>['required','confirmed',PasswordPolicy::rule()]]);
        $status=Password::reset($data,function(User $user,string $password){$user->forceFill(['password'=>Hash::make($password),'must_change_password'=>false,'password_changed_at'=>now()])->setRememberToken(Str::random(60));$user->save();$user->tokens()->delete();event(new PasswordReset($user));});
        if($status!==Password::PASSWORD_RESET)return response()->json(['message'=>'Le lien est invalide ou a expiré.'],422);
        return response()->json(['message'=>'Mot de passe réinitialisé. Vous pouvez vous connecter.']);
    }
}
