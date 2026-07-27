<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
class UserController extends Controller {
    public function __construct(private AuditService $audit) {}
    public function index(Request $request): JsonResponse {
        $users=User::with('roles:id,code,name')->when($request->string('search')->toString(),fn($q,$s)=>$q->where(fn($x)=>$x->where('name','like',"%$s%")->orWhere('email','like',"%$s%")))->orderBy('name')->paginate(20);
        return response()->json($users);
    }
    public function store(Request $request): JsonResponse {
        $data=$request->validate(['name'=>['required','string','max:120'],'email'=>['required','email','max:190','unique:users,email'],'phone'=>['nullable','string','max:40'],'role_ids'=>['array'],'role_ids.*'=>['integer','exists:roles,id']]);
        $temporary=Str::password(16, symbols: true);
        $user=User::create([...$data,'password'=>$temporary,'is_active'=>true,'must_change_password'=>true]);
        $user->roles()->sync(collect($data['role_ids']??[])->mapWithKeys(fn($id)=>[$id=>['scope_type'=>'platform','scope_id'=>null]]));
        $this->audit->record($request,'user.created',$user,[],['name'=>$user->name,'email'=>$user->email]);
        return response()->json(['user'=>$user->load('roles:id,code,name'),'temporary_password'=>$temporary],201);
    }
    public function update(Request $request, User $user): JsonResponse {
        $old=$user->only(['name','email','phone','is_active']);
        $data=$request->validate(['name'=>['sometimes','required','string','max:120'],'email'=>['sometimes','required','email','max:190',Rule::unique('users')->ignore($user->id)],'phone'=>['nullable','string','max:40'],'is_active'=>['sometimes','boolean'],'role_ids'=>['sometimes','array'],'role_ids.*'=>['integer','exists:roles,id']]);
        $user->update(collect($data)->except('role_ids')->all());
        if(array_key_exists('role_ids',$data)) $user->roles()->sync(collect($data['role_ids'])->mapWithKeys(fn($id)=>[$id=>['scope_type'=>'platform','scope_id'=>null]]));
        $this->audit->record($request,'user.updated',$user,$old,$user->only(['name','email','phone','is_active']));
        return response()->json(['user'=>$user->load('roles:id,code,name')]);
    }
    public function resetPassword(Request $request, User $user): JsonResponse {
        $temporary=Str::password(16, symbols: true); $user->update(['password'=>Hash::make($temporary),'must_change_password'=>true]); $user->tokens()->delete();
        $this->audit->record($request,'user.password_reset',$user);
        return response()->json(['temporary_password'=>$temporary]);
    }
    public function roles(): JsonResponse { return response()->json(['roles'=>Role::orderBy('name')->get(['id','code','name'])]); }
}
