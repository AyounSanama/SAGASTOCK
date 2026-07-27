<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class RoleController extends Controller {
 public function __construct(private AuditService $audit){}
 public function index():JsonResponse{return response()->json(['roles'=>Role::with('permissions:id,code,name')->orderBy('name')->get(),'permissions'=>Permission::orderBy('name')->get(['id','code','name'])]);}
 public function store(Request $request):JsonResponse{$data=$request->validate(['code'=>['required','alpha_dash','max:80','unique:roles,code'],'name'=>['required','string','max:120'],'permission_ids'=>['array'],'permission_ids.*'=>['integer','exists:permissions,id']]);$role=Role::create(['code'=>$data['code'],'name'=>$data['name'],'is_system'=>false]);$role->permissions()->sync($data['permission_ids']??[]);$this->audit->record($request,'role.created',$role,[],['code'=>$role->code,'name'=>$role->name]);return response()->json(['role'=>$role->load('permissions:id,code,name')],201);}
 public function update(Request $request,Role $role):JsonResponse{$data=$request->validate(['code'=>['sometimes','required','alpha_dash','max:80',Rule::unique('roles')->ignore($role->id)],'name'=>['sometimes','required','string','max:120'],'permission_ids'=>['sometimes','array'],'permission_ids.*'=>['integer','exists:permissions,id']]);$old=$role->only(['code','name']);$role->update(collect($data)->except('permission_ids')->all());if(array_key_exists('permission_ids',$data))$role->permissions()->sync($data['permission_ids']);$this->audit->record($request,'role.updated',$role,$old,$role->only(['code','name']));return response()->json(['role'=>$role->load('permissions:id,code,name')]);}
 public function destroy(Request $request,Role $role):JsonResponse{if($role->is_system)return response()->json(['message'=>'Un rôle système ne peut pas être supprimé.'],422);if($role->users()->exists())return response()->json(['message'=>'Ce rôle est encore attribué à des utilisateurs.'],422);$this->audit->record($request,'role.deleted',$role,$role->only(['code','name']));$role->delete();return response()->json(status:204);}
}
