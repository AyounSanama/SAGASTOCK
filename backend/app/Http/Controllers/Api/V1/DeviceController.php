<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class DeviceController extends Controller {
 public function __construct(private AuditService $audit){}
 public function index(Request $request):JsonResponse{return response()->json(['devices'=>$request->user()->devices()->latest('last_seen_at')->get(['id','name','platform','last_seen_at','revoked_at'])]);}
 public function revoke(Request $request,Device $device):JsonResponse{
  abort_unless($device->user_id===$request->user()->id,404);$device->update(['revoked_at'=>now()]);$request->user()->tokens()->where('name',$device->id)->delete();$this->audit->record($request,'device.revoked',$device);return response()->json(['message'=>'Appareil révoqué.']);
 }
}
