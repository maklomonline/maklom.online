<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->service = new NotificationService();
    }

    public function test_user_can_view_notifications_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        Event::fake();

        $notification = $this->service->send($this->user, 'test', 'Test notification');

        $this->actingAs($this->user)
            ->patchJson(route('notifications.read', $notification))
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id, 'read_at' => null]);
    }

    public function test_user_can_mark_all_read(): void
    {
        Event::fake();

        $this->service->send($this->user, 'test', 'Notif 1');
        $this->service->send($this->user, 'test', 'Notif 2');

        $this->actingAs($this->user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertDatabaseMissing('notifications', ['user_id' => $this->user->id, 'read_at' => null]);
    }

    public function test_user_can_delete_notification(): void
    {
        Event::fake();

        $notification = $this->service->send($this->user, 'test', 'Delete me');

        $this->actingAs($this->user)
            ->deleteJson(route('notifications.destroy', $notification))
            ->assertOk();

        $this->assertDatabaseMissing('notifications', ['id' => $notification->id]);
    }

    public function test_user_cannot_access_others_notification(): void
    {
        Event::fake();

        $other = User::factory()->create();
        $notification = $this->service->send($other, 'test', 'Not yours');

        $this->actingAs($this->user)
            ->patchJson(route('notifications.read', $notification))
            ->assertForbidden();
    }
}
