<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService {
    public function record(Request $request, string $event, mixed $subject, array $old = [], array $new = []): void {
        AuditLog::create(['user_id'=>$request->user()?->id,'event'=>$event,'auditable_type'=>is_object($subject)?$subject::class:null,
            'auditable_id'=>is_object($subject)?(string)$subject->getKey():null,'old_values'=>$old ?: null,'new_values'=>$new ?: null,
            'ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
    }
}
