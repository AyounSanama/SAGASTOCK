<?php

namespace Tests\Feature\Api;

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
    }
}
