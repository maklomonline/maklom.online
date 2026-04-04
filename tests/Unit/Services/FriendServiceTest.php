<?php

namespace Tests\Unit\Services;

use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestSent;
use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FriendServiceTest extends TestCase
{
    use RefreshDatabase;

    private FriendService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new FriendService();
    }

    public function test_send_request_creates_friendship(): void
    {
        Event::fake();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $friendship = $this->service->sendRequest($from, $to);

        $this->assertEquals('pending', $friendship->status);
        $this->assertDatabaseHas('friendships', ['requester_id' => $from->id, 'addressee_id' => $to->id]);
        Event::assertDispatched(FriendRequestSent::class);
    }

    public function test_send_request_returns_existing_if_duplicate(): void
    {
        Event::fake();
        $from = User::factory()->create();
        $to = User::factory()->create();

        $first = $this->service->sendRequest($from, $to);
        $second = $this->service->sendRequest($from, $to);

        $this->assertEquals($first->id, $second->id);
        $this->assertDatabaseCount('friendships', 1);
    }

    public function test_accept_request_changes_status(): void
    {
        Event::fake();
        $from = User::factory()->create();
        $to = User::factory()->create();
        $friendship = Friendship::create(['requester_id' => $from->id, 'addressee_id' => $to->id, 'status' => 'pending']);

        $this->service->acceptRequest($to, $friendship->id);

        $this->assertDatabaseHas('friendships', ['id' => $friendship->id, 'status' => 'accepted']);
        Event::assertDispatched(FriendRequestAccepted::class);
    }

    public function test_block_creates_blocked_friendship(): void
    {
        Event::fake();
        $blocker = User::factory()->create();
        $blocked = User::factory()->create();

        $this->service->block($blocker, $blocked);

        $this->assertDatabaseHas('friendships', [
            'requester_id' => $blocker->id,
            'addressee_id' => $blocked->id,
            'status' => 'blocked',
            'blocked_by' => $blocker->id,
        ]);
    }

    public function test_are_blocked_returns_true(): void
    {
        Event::fake();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->service->block($a, $b);

        $this->assertTrue($this->service->areBlocked($a, $b));
        $this->assertTrue($this->service->areBlocked($b, $a));
    }

    public function test_get_friends_returns_accepted_only(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $pending = User::factory()->create();

        Friendship::create(['requester_id' => $user->id, 'addressee_id' => $friend->id, 'status' => 'accepted']);
        Friendship::create(['requester_id' => $user->id, 'addressee_id' => $pending->id, 'status' => 'pending']);

        $friends = $this->service->getFriends($user);

        $this->assertCount(1, $friends);
        $this->assertEquals($friend->id, $friends->first()->id);
    }

    public function test_remove_friend_deletes_friendship(): void
    {
        Event::fake();
        $user = User::factory()->create();
        $friend = User::factory()->create();
        Friendship::create(['requester_id' => $user->id, 'addressee_id' => $friend->id, 'status' => 'accepted']);

        $this->service->removeFriend($user, $friend);

        $this->assertDatabaseEmpty('friendships');
    }
}
