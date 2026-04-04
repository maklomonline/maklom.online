<?php

namespace Tests\Feature;

use App\Events\GameEnded;
use App\Events\MoveMade;
use App\Events\PlayerPassed;
use App\Events\PlayerResigned;
use App\Models\Game;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class GameTest extends TestCase
{
    use RefreshDatabase;

    private User $black;
    private User $white;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->black = User::factory()->create();
        $this->white = User::factory()->create();

        UserStat::create(['user_id' => $this->black->id]);
        UserStat::create(['user_id' => $this->white->id]);

        $room = GameRoom::factory()->create([
            'creator_id' => $this->black->id,
            'board_size' => 9,
            'status' => 'playing',
        ]);

        $this->game = Game::factory()->create([
            'room_id' => $room->id,
            'black_player_id' => $this->black->id,
            'white_player_id' => $this->white->id,
            'board_size' => 9,
            'status' => 'active',
            'current_color' => 'black',
        ]);
    }

    public function test_player_can_view_game(): void
    {
        $this->actingAs($this->black)
            ->get(route('games.show', $this->game))
            ->assertOk();
    }

    public function test_black_can_make_move(): void
    {
        Event::fake();

        $response = $this->actingAs($this->black)
            ->postJson(route('games.move', $this->game), ['coordinate' => 'E5']);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('game_moves', ['game_id' => $this->game->id, 'coordinate' => 'E5', 'color' => 'black']);
        Event::assertDispatched(MoveMade::class);
    }

    public function test_white_cannot_move_when_it_is_blacks_turn(): void
    {
        Event::fake();

        $response = $this->actingAs($this->white)
            ->postJson(route('games.move', $this->game), ['coordinate' => 'D4']);

        $response->assertStatus(422);
    }

    public function test_non_player_cannot_make_move(): void
    {
        $other = User::factory()->create();

        $this->actingAs($other)
            ->postJson(route('games.move', $this->game), ['coordinate' => 'A1'])
            ->assertForbidden();
    }

    public function test_player_can_pass(): void
    {
        Event::fake();

        $response = $this->actingAs($this->black)
            ->postJson(route('games.pass', $this->game));

        $response->assertOk()->assertJson(['success' => true]);
        Event::assertDispatched(PlayerPassed::class);
    }

    public function test_two_consecutive_passes_trigger_scoring(): void
    {
        Event::fake();

        $this->actingAs($this->black)->postJson(route('games.pass', $this->game));
        $this->actingAs($this->white)->postJson(route('games.pass', $this->game));

        $this->game->refresh();
        $this->assertEquals('scoring', $this->game->status);
    }

    public function test_player_can_resign(): void
    {
        Event::fake();

        $response = $this->actingAs($this->black)
            ->postJson(route('games.resign', $this->game));

        $response->assertOk()->assertJson(['success' => true]);
        Event::assertDispatched(PlayerResigned::class);

        $this->game->refresh();
        $this->assertEquals('finished', $this->game->status);
        $this->assertEquals($this->white->id, $this->game->winner_id);
    }

    public function test_resign_updates_player_stats(): void
    {
        Event::fake();

        $this->actingAs($this->black)->postJson(route('games.resign', $this->game));

        $this->assertEquals(1, $this->white->stats->fresh()->games_won);
        $this->assertEquals(1, $this->black->stats->fresh()->games_lost);
    }

    public function test_submit_dead_stones_finishes_game(): void
    {
        Event::fake();

        $this->game->update(['status' => 'scoring']);

        $response = $this->actingAs($this->black)
            ->postJson(route('games.scoring', $this->game), ['dead_stones' => []]);

        $response->assertOk();
        Event::assertDispatched(GameEnded::class);
    }

    public function test_cannot_make_move_on_finished_game(): void
    {
        Event::fake();

        $this->game->update(['status' => 'finished']);

        $this->actingAs($this->black)
            ->postJson(route('games.move', $this->game), ['coordinate' => 'A1'])
            ->assertStatus(422);
    }

    public function test_user_can_view_game_history(): void
    {
        $this->actingAs($this->black)
            ->get(route('games.history', $this->black))
            ->assertOk();
    }
}
