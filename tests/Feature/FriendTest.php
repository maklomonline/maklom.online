<?php

namespace Tests\Feature;

use App\Events\FriendRequestAccepted;
use App\Events\FriendRequestSent;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FriendTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    public function test_user_can_view_friends_page(): void
    {
        $this->actingAs($this->user)
            ->get(route('friends.index'))
            ->assertOk();
    }

    public function test_user_can_send_friend_request(): void
    {
        Event::fake();

        $this->actingAs($this->user)
            ->post(route('friends.request', $this->other))
            ->assertRedirect();

        $this->assertDatabaseHas('friendships', [
            'requester_id' => $this->user->id,
            'addressee_id' => $this->other->id,
            'status' => 'pending',
        ]);
        Event::assertDispatched(FriendRequestSent::class);
    }

    public function test_user_cannot_send_request_to_self(): void
    {
        $this->actingAs($this->user)
            ->post(route('friends.request', $this->user))
            ->assertSessionHasErrors('friend');
    }

    public function test_user_can_accept_friend_request(): void
    {
        Event::fake();

        $friendship = Friendship::create([
            'requester_id' => $this->other->id,
            'addressee_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->post(route('friends.accept', $friendship))
            ->assertRedirect();

        $this->assertDatabaseHas('friendships', ['id' => $friendship->id, 'status' => 'accepted']);
        Event::assertDispatched(FriendRequestAccepted::class);
    }

    public function test_user_can_decline_friend_request(): void
    {
        Event::fake();

        $friendship = Friendship::create([
            'requester_id' => $this->other->id,
            'addressee_id' => $this->user->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->user)
            ->post(route('friends.decline', $friendship))
            ->assertRedirect();

        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_user_can_remove_friend(): void
    {
        Event::fake();

        Friendship::create([
            'requester_id' => $this->user->id,
            'addressee_id' => $this->other->id,
            'status' => 'accepted',
        ]);

        $this->actingAs($this->user)
            ->delete(route('friends.remove', $this->other))
            ->assertRedirect();

        $this->assertDatabaseEmpty('friendships');
    }

    public function test_user_can_block_another_user(): void
    {
        Event::fake();

        $this->actingAs($this->user)
            ->post(route('friends.block', $this->other))
            ->assertRedirect();

        $this->assertDatabaseHas('friendships', [
            'requester_id' => $this->user->id,
            'addressee_id' => $this->other->id,
            'status' => 'blocked',
            'blocked_by' => $this->user->id,
        ]);
    }

    public function test_user_can_unblock(): void
    {
        Event::fake();

        Friendship::create([
            'requester_id' => $this->user->id,
            'addressee_id' => $this->other->id,
            'status' => 'blocked',
            'blocked_by' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('friends.unblock', $this->other))
            ->assertRedirect();

        $this->assertDatabaseMissing('friendships', ['status' => 'blocked']);
    }
}
