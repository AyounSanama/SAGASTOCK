<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class AuthController extends Controller {
    public function create(): View { return view('auth.login'); }
    public function store(Request $request): RedirectResponse {
        $credentials=$request->validate(['email'=>['required','email'],'password'=>['required']]);
        if(!Auth::attempt([...$credentials,'is_active'=>true],$request->boolean('remember'))) return back()->withErrors(['email'=>'Identifiants incorrects ou compte dÃƒÂ©sactivÃƒÂ©.'])->onlyInput('email');
        $request->session()->regenerate(); return Auth::user()->must_change_password ? redirect()->route('profile.show')->with('status', 'Vous devez remplacer le mot de passe temporaire.') : redirect()->intended('/dashboard');
    }
    public function destroy(Request $request): RedirectResponse { Auth::logout(); $request->session()->invalidate(); $request->session()->regenerateToken(); return redirect('/login'); }
    public function dashboard(): View {
        $this->authorizeUsers('users.view');
        return view('dashboard.index',['users'=>User::with('roles')->orderBy('name')->paginate(20),'roles'=>Role::orderBy('name')->get()]);
    }
    public function createUser(Request $request): RedirectResponse {
        $this->authorizeUsers('users.manage');
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','unique:users,email'],'phone'=>['nullable','string','max:40'],'role_ids'=>['array'],'role_ids.*'=>['integer','exists:roles,id']]);
        $temporary=Str::password(16,symbols:true); $user=User::create([...$data,'password'=>$temporary,'is_active'=>true,'must_change_password'=>true]);
        $user->roles()->sync(collect($data['role_ids']??[])->mapWithKeys(fn($id)=>[$id=>['scope_type'=>'platform','scope_id'=>null]]));
        return back()->with('success','Utilisateur crÃƒÂ©ÃƒÂ©.')->with('temporary_password',$temporary);
    }
    public function updateUser(Request $request, User $user): RedirectResponse {
        $this->authorizeUsers('users.manage');
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email',Rule::unique('users')->ignore($user->id)],'phone'=>['nullable','string','max:40'],'is_active'=>['nullable','boolean'],'role_ids'=>['array'],'role_ids.*'=>['integer','exists:roles,id']]);
        $user->update([...collect($data)->except('role_ids')->all(),'is_active'=>$request->boolean('is_active')]);
        $user->roles()->sync(collect($data['role_ids']??[])->mapWithKeys(fn($id)=>[$id=>['scope_type'=>'platform','scope_id'=>null]]));
        return back()->with('success','Utilisateur mis ÃƒÂ  jour.');
    }
    public function resetUserPassword(User $user): RedirectResponse {
        $this->authorizeUsers('users.manage'); $temporary=Str::password(16,symbols:true); $user->update(['password'=>$temporary,'must_change_password'=>true]); $user->tokens()->delete();
        return back()->with('success','Mot de passe rÃƒÂ©initialisÃƒÂ©.')->with('temporary_password',$temporary);
    }
    private function authorizeUsers(string $permission): void { abort_unless(Auth::user()?->hasPermission($permission),403); }
}
