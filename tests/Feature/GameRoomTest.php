<?php

namespace Tests\Feature;

use App\Events\LobbyRoomUpdated;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameRoomTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        UserStat::create(['user_id' => $this->user->id]);
    }

    public function test_authenticated_verified_user_can_view_rooms_list(): void
    {
        $this->actingAs($this->user)
            ->get(route('rooms.index'))
            ->assertOk();
    }

    public function test_user_can_create_a_room(): void
    {
        Event::fake();

        $response = $this->actingAs($this->user)->post(route('rooms.store'), [
            'name' => 'ห้องทดสอบ',
            'board_size' => 19,
            'handicap' => 0,
            'komi' => 6.5,
            'clock_type' => 'byoyomi',
            'main_time' => 300,
            'byoyomi_periods' => 5,
            'byoyomi_seconds' => 30,
            'is_private' => false,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('game_rooms', ['name' => 'ห้องทดสอบ', 'creator_id' => $this->user->id]);
        Event::assertDispatched(LobbyRoomUpdated::class);
    }

    public function test_user_can_view_room(): void
    {
        $room = GameRoom::factory()->create(['creator_id' => $this->user->id]);

        $this->actingAs($this->user)
            ->get(route('rooms.show', $room))
            ->assertOk();
    }

    public function test_another_user_can_join_room_and_game_starts(): void
    {
        Event::fake();

        $creator = User::factory()->create();
        UserStat::create(['user_id' => $creator->id]);

        $room = GameRoom::factory()->create([
            'creator_id' => $creator->id,
            'board_size' => 9,
            'clock_type' => 'byoyomi',
            'main_time' => 60,
            'byoyomi_periods' => 3,
            'byoyomi_seconds' => 10,
            'komi' => 6.5,
            'handicap' => 0,
            'status' => 'waiting',
        ]);

        $joiner = $this->user;

        $response = $this->actingAs($joiner)->post(route('rooms.join', $room));

        $response->assertRedirect();
        $this->assertDatabaseHas('games', ['room_id' => $room->id]);
    }

    public function test_creator_cannot_join_own_room(): void
    {
        $room = GameRoom::factory()->create(['creator_id' => $this->user->id, 'status' => 'waiting']);

        $response = $this->actingAs($this->user)->post(route('rooms.join', $room));

        $response->assertSessionHasErrors('room');
    }

    public function test_creator_can_cancel_waiting_room(): void
    {
        Event::fake();

        $room = GameRoom::factory()->create(['creator_id' => $this->user->id, 'status' => 'waiting']);

        $this->actingAs($this->user)
            ->delete(route('rooms.leave', $room))
            ->assertRedirect(route('lobby'));

        $this->assertDatabaseHas('game_rooms', ['id' => $room->id, 'status' => 'cancelled']);
    }

    public function test_user_cannot_destroy_room_they_did_not_create(): void
    {
        $other = User::factory()->create();
        $room = GameRoom::factory()->create(['creator_id' => $other->id, 'status' => 'waiting']);

        $this->actingAs($this->user)
            ->delete(route('rooms.destroy', $room))
            ->assertForbidden();
    }
}
