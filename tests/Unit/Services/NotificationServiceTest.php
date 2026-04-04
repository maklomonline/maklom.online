<?php

namespace Tests\Unit\Services;

use App\Events\NotificationCreated;
use App\Models\AppNotification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private NotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new NotificationService();
    }

    public function test_send_creates_notification(): void
    {
        Event::fake();
        $user = User::factory()->create();

        $notification = $this->service->send($user, 'friend_request', 'มีคำขอเพื่อนใหม่');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'type' => 'friend_request',
            'title' => 'มีคำขอเพื่อนใหม่',
        ]);

        Event::assertDispatched(NotificationCreated::class);
    }

    public function test_get_unread_count(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $this->service->send($user, 'test', 'Notification 1');
        $this->service->send($user, 'test', 'Notification 2');

        $this->assertEquals(2, $this->service->getUnreadCount($user));
    }

    public function test_mark_all_read(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $this->service->send($user, 'test', 'Notification 1');
        $this->service->send($user, 'test', 'Notification 2');

        $this->service->markAllRead($user);

        $this->assertEquals(0, $this->service->getUnreadCount($user));
        $this->assertDatabaseMissing('notifications', ['user_id' => $user->id, 'read_at' => null]);
    }
}
