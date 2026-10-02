<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class AuditController extends Controller {public function index(Request $request):JsonResponse{$logs=AuditLog::with('user:id,name,email')->withoutHealthData()->when($request->string('event')->toString(),fn($q,$v)=>$q->where('event','like',"$v%"))->when($request->date('from'),fn($q,$v)=>$q->whereDate('created_at','>=',$v))->when($request->date('to'),fn($q,$v)=>$q->whereDate('created_at','<=',$v))->latest()->paginate(50);return response()->json($logs);}}
