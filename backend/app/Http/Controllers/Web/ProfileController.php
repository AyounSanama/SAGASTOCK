<?php
namespace App\Http\Controllers\Web;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
class ProfileController extends Controller {
 public function show(Request $request):View{return view('profile.show',['user'=>$request->user(),'devices'=>$request->user()->devices()->latest('last_seen_at')->get()]);}
 public function password(Request $request):RedirectResponse{$data=$request->validate(['current_password'=>['required','current_password'],'password'=>['required','min:12','confirmed']]);$request->user()->update(['password'=>Hash::make($data['password']),'must_change_password'=>false,'password_changed_at'=>now()]);return back()->with('status','Mot de passe modifié.');}
 public function revoke(Request $request,Device $device):RedirectResponse{abort_unless($device->user_id===$request->user()->id,404);$device->update(['revoked_at'=>now()]);$request->user()->tokens()->where('name',$device->id)->delete();return back()->with('status','Appareil révoqué.');}
}
