<?php

namespace Tests\Feature\Web;

use App\Models\User;
use App\Notifications\OperationalNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_center_lists_paginates_and_reads_only_the_recipient_records(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach (range(1, 21) as $number) {
            $user->notify(new OperationalNotification(['title' => "Alerte $number", 'message' => 'Action requise', 'action_path' => '/inventories']));
        }
        $other->notify(new OperationalNotification(['title' => 'Privée']));
        $this->assertDatabaseCount('notifications', 22);
        $response = $this->actingAs($user)->getJson('/profile/notifications')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 21)->assertJsonPath('next_page', 2);
        $this->getJson('/profile/notifications?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('next_page', null);
        $id = $response->json('data.0.id');
        $this->postJson("/profile/notifications/$id/read")->assertOk()->assertJsonPath('unread_count', 20);
        $this->postJson("/profile/notifications/$id/read")->assertOk()->assertJsonPath('unread_count', 20);
        $this->postJson('/profile/notifications/'.$other->notifications()->first()->id.'/read')->assertNotFound();
        $this->postJson('/profile/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertSame(1, $other->unreadNotifications()->count());
        $this->getJson('/profile/notifications')->assertJsonPath('data.0.read', true);
        // A valid notification link does not grant access to its destination.
        $this->get('/inventories')->assertForbidden();
    }

    public function test_external_or_ambiguous_links_are_not_exposed(): void
    {
        $user = User::factory()->create();
        foreach (['https://example.org', '//example.org', '/\\example.org', " /inventories", 'javascript:alert(1)'] as $path) {
            $user->notify(new OperationalNotification(['title' => 'Lien', 'action_path' => $path]));
        }
        $data = $this->actingAs($user)->getJson('/profile/notifications')->assertOk()->json('data');
        foreach ($data as $item) $this->assertNull($item['action_path']);
    }

    public function test_empty_center_and_guest_authentication(): void
    {
        $this->getJson('/profile/notifications')->assertUnauthorized();
        $this->postJson('/profile/notifications/read-all')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson('/profile/notifications')->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('unread_count', 0);
    }
}
