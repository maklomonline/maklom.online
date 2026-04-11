<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameMove;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\SgfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameReplayTest extends TestCase
{
    use RefreshDatabase;

    private User $black;
    private User $white;
    private Game $game;
    private SgfService $sgfService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->black = User::factory()->create();
        $this->white = User::factory()->create();

        $room = GameRoom::factory()->create([
            'creator_id' => $this->black->id,
            'board_size' => 9,
            'status' => 'playing',
        ]);

        $this->game = Game::factory()->create([
            'room_id' => $this->black->id,
            'black_player_id' => $this->black->id,
            'white_player_id' => $this->white->id,
            'board_size' => 9,
            'status' => 'finished',
            'current_color' => 'black',
            'result' => 'B+5.5',
        ]);

        $this->sgfService = app(SgfService::class);
    }

    public function test_finished_game_shows_replay_interface(): void
    {
        $response = $this->actingAs($this->black)
            ->get(route('games.show', $this->game));

        $response->assertOk()
            ->assertViewHas('boardStates')
            ->assertViewHas('annotations');
    }

    public function test_board_states_are_generated_for_replay(): void
    {
        // Add some moves to the game
        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 1,
            'color' => 'black',
            'coordinate' => 'D4',
        ]);

        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 2,
            'color' => 'white',
            'coordinate' => 'D5',
        ]);

        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 3,
            'color' => 'black',
            'coordinate' => 'C4',
        ]);

        $boardStates = $this->sgfService->computeBoardStates($this->game);

        $this->assertNotEmpty($boardStates);
        $this->assertCount(4, $boardStates); // Initial + 3 moves
        
        // Check that initial board is empty
        $this->assertEquals(array_fill(0, 81, 0), $boardStates[0]);
        
        // Check that move boards are different from initial
        $this->assertNotEquals($boardStates[0], $boardStates[1]);
        $this->assertNotEquals($boardStates[1], $boardStates[2]);
        $this->assertNotEquals($boardStates[2], $boardStates[3]);
    }

    public function test_replay_handles_invalid_moves_gracefully(): void
    {
        // Add an invalid move (coordinate doesn't exist on 9x9 board)
        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 1,
            'color' => 'black',
            'coordinate' => 'Z99', // Invalid coordinate
        ]);

        // This should not throw an exception
        $boardStates = $this->sgfService->computeBoardStates($this->game);
        
        $this->assertNotEmpty($boardStates);
        $this->assertCount(2, $boardStates); // Initial + 1 (invalid) move
    }

    public function test_replay_handles_pass_moves(): void
    {
        // Add some moves including passes
        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 1,
            'color' => 'black',
            'coordinate' => 'D4',
        ]);

        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 2,
            'color' => 'white',
            'coordinate' => null, // Pass
        ]);

        GameMove::create([
            'game_id' => $this->game->id,
            'move_number' => 3,
            'color' => 'black',
            'coordinate' => 'D5',
        ]);

        $boardStates = $this->sgfService->computeBoardStates($this->game);

        $this->assertNotEmpty($boardStates);
        $this->assertCount(4, $boardStates);
        
        // Pass move should not change the board state
        $this->assertEquals($boardStates[1], $boardStates[2]);
    }
}