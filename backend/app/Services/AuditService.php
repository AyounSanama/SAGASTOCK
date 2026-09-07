<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService {
    public function record(Request $request, string $event, mixed $subject, array $old = [], array $new = []): void {
        AuditLog::create(['user_id'=>$request->user()?->id,'event'=>$event,'auditable_type'=>is_object($subject)?$subject::class:null,
            'auditable_id'=>is_object($subject)?(string)$subject->getKey():null,
            'old_values'=>$old ? $this->redactSensitiveValues($old) : null,
            'new_values'=>$new ? $this->redactSensitiveValues($new) : null,
            'ip_address'=>$request->ip(),'user_agent'=>$request->userAgent()]);
    }

    private function redactSensitiveValues(array $values): array
    {
        $sensitiveKeys = [
            'password', 'password_confirmation', 'current_password', 'remember_token',
            'token', 'access_token', 'authorization', 'secret',
            'attachment', 'attachment_path', 'attachment_original_name',
            'prescription_attachment_path', 'prescription_attachment_original_name',
            'allergies', 'clinical_notes', 'diagnosis',
        ];

        foreach ($values as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redactSensitiveValues($value);
            }
        }

        return $values;
    }
}
