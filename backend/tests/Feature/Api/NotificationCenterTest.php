<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_only_reads_their_own_notifications(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $user->notify(new OperationalNotification(['title' => 'Mon alerte', 'message' => 'Action requise', 'action_path' => '/inventories']));
        $other->notify(new OperationalNotification(['title' => 'Alerte privée', 'message' => 'Invisible']));

        Sanctum::actingAs($user);
        $response = $this->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonPath('unread_count', 1)->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Mon alerte');
        $id = $response->json('data.0.id');
        $this->postJson("/api/v1/notifications/{$id}/read")->assertOk();
        $this->getJson('/api/v1/notifications')->assertJsonPath('unread_count', 0)->assertJsonPath('data.0.read', true);

        $foreignId = $other->notifications()->first()->id;
        $this->postJson("/api/v1/notifications/{$foreignId}/read")->assertNotFound();
    }
}
