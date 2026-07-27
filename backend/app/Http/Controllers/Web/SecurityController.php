<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
class SecurityController extends Controller {
 public function index(Request $request):View{$this->allow('roles.manage');$logs=AuditLog::with('user:id,name,email')->when($request->string('event')->toString(),fn($q,$v)=>$q->where('event','like',"$v%"))->latest()->paginate(30);return view('security.index',['roles'=>Role::with('permissions')->orderBy('name')->get(),'permissions'=>Permission::orderBy('name')->get(),'logs'=>$logs]);}
 public function store(Request $request):RedirectResponse{$this->allow('roles.manage');$data=$request->validate(['code'=>['required','alpha_dash','unique:roles,code'],'name'=>['required','string','max:120'],'permission_ids'=>['array'],'permission_ids.*'=>['integer','exists:permissions,id']]);$role=Role::create(['code'=>$data['code'],'name'=>$data['name'],'is_system'=>false]);$role->permissions()->sync($data['permission_ids']??[]);return back()->with('status','Rôle créé.');}
 public function update(Request $request,Role $role):RedirectResponse{$this->allow('roles.manage');$data=$request->validate(['code'=>['required','alpha_dash',Rule::unique('roles')->ignore($role->id)],'name'=>['required','string','max:120'],'permission_ids'=>['array'],'permission_ids.*'=>['integer','exists:permissions,id']]);$role->update(['code'=>$data['code'],'name'=>$data['name']]);$role->permissions()->sync($data['permission_ids']??[]);return back()->with('status','Rôle mis à jour.');}
 public function destroy(Role $role):RedirectResponse{$this->allow('roles.manage');if($role->is_system||$role->users()->exists())return back()->withErrors(['role'=>'Ce rôle système ou attribué ne peut pas être supprimé.']);$role->delete();return back()->with('status','Rôle supprimé.');}
 private function allow(string $permission):void{abort_unless(Auth::user()?->hasPermission($permission),403);}
}
