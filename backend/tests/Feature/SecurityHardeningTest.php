<?php

namespace Tests\Feature;

use App\Models\Dispensation;
use App\Models\AuditLog;
use App\Models\Prescription;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_responses_include_non_breaking_security_headers(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
    }

    public function test_mobile_login_issues_a_bounded_token(): void
    {
        $user = User::factory()->create([
            'email' => 'security@example.org',
            'password' => Hash::make('Secure@123'),
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'Secure@123',
            'device_name' => 'Security test',
            'device_id' => fake()->uuid(),
            'platform' => 'android',
        ])->assertOk();

        $token = PersonalAccessToken::firstOrFail();
        $this->assertNotNull($token->expires_at);
        $this->assertTrue($token->expires_at->between(now()->addDays(29), now()->addDays(31)));
    }

    public function test_private_attachment_paths_are_not_serialized(): void
    {
        $prescription = new Prescription([
            'attachment_path' => 'private/prescriptions/internal.jpg',
            'attachment_original_name' => 'patient-name.jpg',
        ]);
        $dispensation = new Dispensation([
            'prescription_attachment_path' => 'private/dispensations/internal.jpg',
            'prescription_attachment_original_name' => 'patient-name.jpg',
        ]);

        $this->assertArrayNotHasKey('attachment_path', $prescription->toArray());
        $this->assertArrayNotHasKey('attachment_original_name', $prescription->toArray());
        $this->assertArrayNotHasKey('prescription_attachment_path', $dispensation->toArray());
        $this->assertArrayNotHasKey('prescription_attachment_original_name', $dispensation->toArray());
    }

    public function test_audit_logs_redact_credentials_and_patient_health_data_recursively(): void
    {
        $user = User::factory()->create();
        $request = Request::create('/api/v1/security-test', 'POST');
        $request->setUserResolver(fn () => $user);

        app(AuditService::class)->record($request, 'security.redaction-tested', $user, [], [
            'name' => 'Valeur métier conservée',
            'password' => 'NeverStoreThis',
            'patient' => [
                'clinical_notes' => 'Donnée médicale sensible',
                'allergies' => 'Pénicilline',
            ],
        ]);

        $values = AuditLog::where('event', 'security.redaction-tested')->firstOrFail()->new_values;
        $this->assertSame('Valeur métier conservée', $values['name']);
        $this->assertSame('[REDACTED]', $values['password']);
        $this->assertSame('[REDACTED]', $values['patient']['clinical_notes']);
        $this->assertSame('[REDACTED]', $values['patient']['allergies']);
    }
}
