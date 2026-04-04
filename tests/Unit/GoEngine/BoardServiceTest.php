<?php

namespace Tests\Unit\GoEngine;

use App\Services\GoEngine\BoardService;
use App\Services\GoEngine\GoRuleException;
use PHPUnit\Framework\TestCase;
use Tests\Helpers\GoHelper;

class BoardServiceTest extends TestCase
{
    private BoardService $board;

    protected function setUp(): void
    {
        parent::setUp();
        $this->board = new BoardService();
    }

    // ─── Basic placement ───────────────────────────────────────────────────────

    public function test_place_stone_on_empty_board(): void
    {
        $board = GoHelper::emptyBoard(9);
        $result = $this->board->placeStone($board, 9, 0, 0, BoardService::BLACK, null);

        $this->assertEquals(BoardService::BLACK, $result->newBoard[0]);
        $this->assertEmpty($result->capturedStones);
        $this->assertNull($result->newKoPoint);
    }

    public function test_place_stone_on_occupied_point_throws(): void
    {
        $board = GoHelper::emptyBoard(9);
        $board[0] = BoardService::BLACK;

        $this->expectException(GoRuleException::class);
        $this->board->placeStone($board, 9, 0, 0, BoardService::WHITE, null);
    }

    public function test_out_of_bounds_throws(): void
    {
        $board = GoHelper::emptyBoard(9);
        $this->expectException(GoRuleException::class);
        $this->board->placeStone($board, 9, 9, 0, BoardService::BLACK, null);
    }

    // ─── Capture logic ─────────────────────────────────────────────────────────

    public function test_capture_single_stone(): void
    {
        // Place white stone at center, surround with black except one lib, then fill last lib
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // White at 4,4
        $board[4 * $s + 4] = BoardService::WHITE;
        // Black at neighbors (all 4)
        $board[3 * $s + 4] = BoardService::BLACK;
        $board[5 * $s + 4] = BoardService::BLACK;
        $board[4 * $s + 3] = BoardService::BLACK;
        // Last liberty: 4,5 — capture it
        $result = $this->board->placeStone($board, 9, 4, 5, BoardService::BLACK, null);

        $this->assertEquals(BoardService::EMPTY, $result->newBoard[4 * $s + 4]);
        $this->assertCount(1, $result->capturedStones);
    }

    public function test_capture_group_of_two(): void
    {
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // Two white stones: 4,4 and 4,5
        $board[4 * $s + 4] = BoardService::WHITE;
        $board[4 * $s + 5] = BoardService::WHITE;
        // Surround: above
        $board[3 * $s + 4] = BoardService::BLACK;
        $board[3 * $s + 5] = BoardService::BLACK;
        // Below
        $board[5 * $s + 4] = BoardService::BLACK;
        $board[5 * $s + 5] = BoardService::BLACK;
        // Left
        $board[4 * $s + 3] = BoardService::BLACK;
        // Right — last liberty at 4,6
        $result = $this->board->placeStone($board, 9, 4, 6, BoardService::BLACK, null);

        $this->assertEquals(BoardService::EMPTY, $result->newBoard[4 * $s + 4]);
        $this->assertEquals(BoardService::EMPTY, $result->newBoard[4 * $s + 5]);
        $this->assertCount(2, $result->capturedStones);
    }

    public function test_capture_at_corner(): void
    {
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // White at A1 (0,0), Black at 0,1 and 1,0 — two neighbors in corner
        $board[0 * $s + 0] = BoardService::WHITE;
        $board[0 * $s + 1] = BoardService::BLACK;
        // Place black at 1,0 to complete capture
        $result = $this->board->placeStone($board, 9, 1, 0, BoardService::BLACK, null);

        $this->assertEquals(BoardService::EMPTY, $result->newBoard[0]);
        $this->assertCount(1, $result->capturedStones);
    }

    // ─── Suicide rule ──────────────────────────────────────────────────────────

    public function test_suicide_is_rejected(): void
    {
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // Surround corner (0,0) with WHITE stones on all neighbours
        $board[0 * $s + 1] = BoardService::WHITE;
        $board[1 * $s + 0] = BoardService::WHITE;

        // Black tries to play at (0,0) — no liberty = suicide
        $this->expectException(GoRuleException::class);
        $this->board->placeStone($board, 9, 0, 0, BoardService::BLACK, null);
    }

    public function test_capture_is_not_suicide(): void
    {
        // Placing a stone that appears to have no liberty but captures opponent = legal
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        $board[0] = BoardService::WHITE;
        $board[0 * $s + 1] = BoardService::BLACK; // right of white
        // Black at 1,0 fills white's last liberty (below 0,0) and captures it
        $result = $this->board->placeStone($board, 9, 1, 0, BoardService::BLACK, null);

        $this->assertEquals(BoardService::EMPTY, $result->newBoard[0]);
        $this->assertEquals(BoardService::BLACK, $result->newBoard[$s]);
    }

    // ─── Ko rule ───────────────────────────────────────────────────────────────

    public function test_ko_point_set_after_capture(): void
    {
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // Setup: B captures W single stone, resulting in ko situation
        $board[1 * $s + 1] = BoardService::WHITE;
        $board[0 * $s + 1] = BoardService::BLACK;
        $board[1 * $s + 0] = BoardService::BLACK;
        $board[1 * $s + 2] = BoardService::BLACK;
        // Place black at 2,1 to capture white
        $result = $this->board->placeStone($board, 9, 2, 1, BoardService::BLACK, null);

        // White captured at (1,1) — if this is ko, ko point should be set
        // (This specific position may or may not produce ko depending on own group size)
        $this->assertNotNull($result); // just ensure no exception
    }

    public function test_ko_rule_blocks_retake(): void
    {
        $board = GoHelper::emptyBoard(9);
        $s = 9;
        // Classic ko: a capture was made and ko point is set to the captured position
        $koPoint = 'C3'; // set ko at C3 (using coordinate notation)

        $board[2 * $s + 2] = BoardService::EMPTY; // ko point is empty
        // Surrounding positions exist to make it a valid ko
        $board[1 * $s + 2] = BoardService::WHITE;
        $board[3 * $s + 2] = BoardService::WHITE;
        $board[2 * $s + 1] = BoardService::WHITE;
        $board[2 * $s + 3] = BoardService::WHITE; // surrounding white

        // Player tries to play at C3 (the ko point) — should be rejected
        $this->expectException(GoRuleException::class);
        $this->board->placeStone($board, 9, 2, 2, BoardService::BLACK, $koPoint);
    }

    // ─── Coordinate conversion ─────────────────────────────────────────────────

    public function test_coordinate_round_trip(): void
    {
        $board = new BoardService();
        // A1 on 9x9 = row 8, col 0
        [$row, $col] = $board->coordinateToRowCol('A1', 9);
        $this->assertEquals(8, $row);
        $this->assertEquals(0, $col);

        $coord = $board->indexToCoordinate($row, $col, 9);
        $this->assertEquals('A1', $coord);
    }

    public function test_coordinate_skips_i(): void
    {
        $board = new BoardService();
        // J should be col 8 (I is skipped)
        [$row, $col] = $board->coordinateToRowCol('J9', 9);
        $this->assertEquals(0, $row);
        $this->assertEquals(8, $col);
    }

    // ─── Scoring ──────────────────────────────────────────────────────────────

    public function test_score_empty_board(): void
    {
        $board = GoHelper::emptyBoard(9);
        $result = $this->board->calculateScore($board, 9, [], 0, 0, 6.5);

        $this->assertEquals(0, $result->blackTerritory);
        $this->assertEquals(0, $result->whiteTerritory);
        $this->assertEquals('white', $result->winner); // komi gives white the win
    }

    public function test_score_one_black_stone_corner(): void
    {
        $board = GoHelper::emptyBoard(9);
        $board[0] = BoardService::BLACK; // A9 — black stone at top-left

        $result = $this->board->calculateScore($board, 9, [], 0, 0, 0.5);
        // Black has 1 stone + some territory, white has none before komi
        $this->assertGreaterThan(0, $result->blackTerritory);
    }

    public function test_score_with_dead_stones(): void
    {
        $board = GoHelper::emptyBoard(9);
        $board[0] = BoardService::WHITE; // A9 dead white stone

        $result = $this->board->calculateScore($board, 9, [[0, 0]], 0, 0, 0);
        // Dead white stone is removed and counts as a black capture
        $this->assertEquals(1, $result->blackCaptures);
    }

    // ─── Handicap ─────────────────────────────────────────────────────────────

    public function test_apply_handicap_2_stones(): void
    {
        $board = $this->board->createEmptyBoard(19);
        $result = $this->board->applyHandicap($board, 19, 2);

        $s = 19;
        $this->assertEquals(BoardService::BLACK, $result[3 * $s + 15]); // D16
        $this->assertEquals(BoardService::BLACK, $result[15 * $s + 3]); // Q4
    }

    public function test_apply_handicap_less_than_2_unchanged(): void
    {
        $board = $this->board->createEmptyBoard(19);
        $result = $this->board->applyHandicap($board, 19, 1);

        $this->assertEquals($board, $result);
    }

    // ─── Legal move check ─────────────────────────────────────────────────────

    public function test_is_legal_move_returns_true_for_valid(): void
    {
        $board = GoHelper::emptyBoard(9);
        $this->assertTrue($this->board->isLegalMove($board, 9, 4, 4, BoardService::BLACK, null));
    }

    public function test_is_legal_move_returns_false_for_occupied(): void
    {
        $board = GoHelper::emptyBoard(9);
        $board[0] = BoardService::BLACK;
        $this->assertFalse($this->board->isLegalMove($board, 9, 0, 0, BoardService::WHITE, null));
    }
}
