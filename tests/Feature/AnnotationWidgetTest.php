<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\GameAnnotation;
use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnotationWidgetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        
        $black = User::factory()->create();
        $white = User::factory()->create();

        $room = GameRoom::factory()->create([
            'creator_id' => $black->id,
            'board_size' => 9,
            'status' => 'playing',
        ]);

        $this->game = Game::factory()->create([
            'room_id' => $room->id,
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => 9,
            'status' => 'finished',
            'current_color' => 'black',
            'result' => 'B+5.5',
        ]);
    }

    public function test_annotation_widget_shows_create_button_for_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('games.show', $this->game));

        $response->assertOk()
            ->assertSee('Annotation')
            ->assertSee('+ สร้างใหม่')
            ->assertSee(route('games.annotation.create', $this->game), false);
    }

    public function test_annotation_editor_route_is_available(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('games.annotation.create', $this->game));

        $response->assertOk()
            ->assertSee('บันทึก annotation')
            ->assertSee('สร้างสาขา');
    }

    public function test_annotation_can_be_saved_with_payload(): void
    {
        $payload = [
            'version' => 2,
            'last_position_key' => 'base-1',
            'positions' => [
                'base-1' => [
                    'comment' => 'จังหวะสำคัญ',
                    'marks' => [
                        ['type' => 'triangle', 'coordinate' => 'D4', 'text' => null],
                    ],
                    'children' => ['node-1'],
                ],
                'node-1' => [
                    'parent' => 'base-1',
                    'color' => 'white',
                    'coordinate' => 'E4',
                    'is_pass' => false,
                    'comment' => 'แตกสาขา',
                    'marks' => [
                        ['type' => 'label', 'coordinate' => 'E4', 'text' => 'A'],
                    ],
                    'children' => [],
                    'order' => 0,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->postJson(route('games.annotation.store', $this->game), [
                'title' => 'รีวิวของฉัน',
                'payload' => $payload,
            ]);

        $response->assertCreated()
            ->assertJsonPath('title', 'รีวิวของฉัน');

        $annotation = GameAnnotation::first();

        $this->assertNotNull($annotation);
        $this->assertSame('รีวิวของฉัน', $annotation->title);
        $this->assertSame($this->game->id, $annotation->game_id);
        $this->assertSame($this->user->id, $annotation->user_id);
        $this->assertSame('base-1', $annotation->last_position_key);
        $this->assertSame(2, $annotation->positions_count);
        $this->assertSame('จังหวะสำคัญ', $annotation->payload['positions']['base-1']['comment']);
    }

    public function test_existing_annotation_can_be_opened_from_widget(): void
    {
        $annotation = GameAnnotation::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'title' => 'รีวิวลำดับแรก',
            'payload' => [
                'version' => 2,
                'last_position_key' => 'base-0',
                'positions' => [],
            ],
            'positions_count' => 0,
            'last_position_key' => 'base-0',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('games.show', $this->game));

        $response->assertOk()
            ->assertSee('รีวิวลำดับแรก')
            ->assertSee(route('games.annotation.show', [$this->game, $annotation]), false);
    }

    public function test_guest_user_is_redirected_to_login(): void
    {
        $this->get(route('games.show', $this->game))->assertRedirect('/login');
        $this->get(route('games.annotation.create', $this->game))->assertRedirect('/login');
    }
}
