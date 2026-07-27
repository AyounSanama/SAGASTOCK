<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
class PasswordController extends Controller {
    public function __construct(private AuditService $audit) {}
    public function update(Request $request): JsonResponse {
        $data=$request->validate(['current_password'=>['required','current_password:sanctum'],'password'=>['required','string','min:12','confirmed']]);
        $request->user()->update(['password'=>Hash::make($data['password']),'must_change_password'=>false,'password_changed_at'=>now()]);
        $this->audit->record($request,'user.password_changed',$request->user());
        return response()->json(['message'=>'Mot de passe modifié.']);
    }
}
