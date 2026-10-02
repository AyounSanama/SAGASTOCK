<?php
namespace App\Services;
use App\Models\AuditLog;
use Illuminate\Http\Request;
class AuditService {
    public function record(Request $request, string $event, mixed $subject, array $old = [], array $new = []): void {
        // S-05 : pour un patient, une ordonnance ou une dispensation, seuls les NOMS
        // des champs modifiés sont conservés, jamais leurs valeurs.
        if (AuditLog::concernsHealthData($event, is_object($subject) ? $subject::class : null)) {
            $fields = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
            [$old, $new] = [[], $fields === [] ? [] : ['champs_modifies' => $fields]];
        }
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
            // Identité et santé du patient : jamais en clair dans le journal.
            'first_name', 'last_name', 'date_of_birth', 'sex', 'phone', 'address', 'external_identifier',
            'notes', 'prescriber_name', 'clinical_validation_notes', 'rejection_reason',
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
