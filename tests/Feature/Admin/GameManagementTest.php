<?php

namespace Tests\Feature\Admin;

use App\Events\GameEnded;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admin_can_view_games_list(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.games.index'))
            ->assertOk();
    }

    public function test_admin_can_abort_active_game(): void
    {
        Event::fake();

        $black = User::factory()->create();
        $white = User::factory()->create();
        UserStat::create(['user_id' => $black->id]);
        UserStat::create(['user_id' => $white->id]);

        $room = GameRoom::factory()->create(['creator_id' => $black->id, 'status' => 'playing']);
        $game = Game::factory()->create([
            'room_id' => $room->id,
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.games.abort', $game))
            ->assertRedirect();

        $this->assertDatabaseHas('games', ['id' => $game->id, 'status' => 'aborted']);
        $this->assertDatabaseHas('admin_logs', [
            'admin_id' => $this->admin->id,
            'action' => 'abort_game',
        ]);
        Event::assertDispatched(GameEnded::class);
    }

    public function test_non_admin_cannot_abort_game(): void
    {
        $regular = User::factory()->create();
        $black = User::factory()->create();
        $white = User::factory()->create();
        UserStat::create(['user_id' => $black->id]);
        UserStat::create(['user_id' => $white->id]);

        $room = GameRoom::factory()->create(['creator_id' => $black->id]);
        $game = Game::factory()->create([
            'room_id' => $room->id,
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
        ]);

        $this->actingAs($regular)
            ->post(route('admin.games.abort', $game))
            ->assertForbidden();
    }
}
