<?php

namespace Tests\Feature\Api;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class IdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_mutating_api_request_is_executed_only_once(): void
    {
        $calls = 0;
        Route::post('/api/v1/test-idempotency', function () use (&$calls) {
            $calls++;

            return response()->json(['calls' => $calls], 201);
        })->middleware(['auth:sanctum', 'idempotency']);

        Sanctum::actingAs(User::factory()->create());
        $key = '3d6f0a66-7a62-4cb1-999c-81435c806997';

        $this->postJson('/api/v1/test-idempotency', [], ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertJsonPath('calls', 1);

        $this->postJson('/api/v1/test-idempotency', [], ['Idempotency-Key' => $key])
            ->assertCreated()
            ->assertHeader('Idempotency-Replayed', 'true')
            ->assertJsonPath('calls', 1);

        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('api_idempotency_keys', 1);

        $this->postJson('/api/v1/test-idempotency', ['changed' => true], ['Idempotency-Key' => $key])
            ->assertConflict();
    }

    public function test_refused_operation_is_reexamined_when_the_phone_retries(): void
    {
        $frozen = true;
        $calls = 0;
        Route::post('/api/v1/test-idempotency-retry', function () use (&$frozen, &$calls) {
            $calls++;
            abort_if($frozen, 423, 'Le stock de ce site est gelé par un inventaire en cours.');

            return response()->json(['calls' => $calls], 201);
        })->middleware(['auth:sanctum', 'idempotency']);

        Sanctum::actingAs(User::factory()->create());
        $key = '5d6f0a66-7a62-4cb1-999c-81435c806999';

        $this->postJson('/api/v1/test-idempotency-retry', [], ['Idempotency-Key' => $key])->assertStatus(423);
        $frozen = false;
        $this->postJson('/api/v1/test-idempotency-retry', [], ['Idempotency-Key' => $key])->assertCreated();
        // Réussite enregistrée : un nouvel envoi est rejoué, sans doublon.
        $this->postJson('/api/v1/test-idempotency-retry', [], ['Idempotency-Key' => $key])
            ->assertCreated()->assertHeader('Idempotency-Replayed', 'true');

        $this->assertSame(2, $calls);
        $this->assertDatabaseHas('api_idempotency_keys', ['key' => $key, 'response_status' => 201]);
    }

    public function test_cached_response_is_rejected_after_authorization_context_changes(): void
    {
        Route::post('/api/v1/test-idempotency-scope', fn () => response()->json(['private' => true]))
            ->middleware(['auth:sanctum', 'idempotency']);

        $user = User::factory()->create();
        Sanctum::actingAs($user);
        $key = '1d6f0a66-7a62-4cb1-999c-81435c806998';

        $this->postJson('/api/v1/test-idempotency-scope', [], ['Idempotency-Key' => $key])
            ->assertOk();

        $permission = Permission::create(['code' => 'scope.changed', 'name' => 'Scope changed']);
        $user->directPermissions()->attach($permission);

        $this->postJson('/api/v1/test-idempotency-scope', [], ['Idempotency-Key' => $key])
            ->assertConflict();
    }
}
