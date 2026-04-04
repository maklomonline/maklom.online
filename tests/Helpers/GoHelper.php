<?php

namespace Tests\Helpers;

use App\Services\GoEngine\BoardService;

class GoHelper
{
    public static function emptyBoard(int $size = 9): array
    {
        return array_fill(0, $size * $size, BoardService::EMPTY);
    }

    /**
     * Build a board from a string representation.
     * Use '.' for empty, 'B' for black, 'W' for white.
     */
    public static function fromString(string $layout, int $size): array
    {
        $layout = preg_replace('/\s+/', '', $layout);
        $board = array_fill(0, $size * $size, BoardService::EMPTY);
        for ($i = 0; $i < strlen($layout) && $i < $size * $size; $i++) {
            $board[$i] = match ($layout[$i]) {
                'B' => BoardService::BLACK,
                'W' => BoardService::WHITE,
                default => BoardService::EMPTY,
            };
        }

        return $board;
    }

    /**
     * Build a position where a single white stone at (row,col) is surrounded.
     * Leaves one liberty open.
     */
    public static function surroundedStone9(int $row, int $col): array
    {
        $board = static::emptyBoard(9);
        $size = 9;
        // Place white stone
        $board[$row * $size + $col] = BoardService::WHITE;
        // Surround with black except one liberty
        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dr, $dc]) {
            $r = $row + $dr;
            $c = $col + $dc;
            if ($r >= 0 && $r < $size && $c >= 0 && $c < $size) {
                $board[$r * $size + $c] = BoardService::BLACK;
            }
        }

        return $board;
    }

    /**
     * Ko position: classic ko setup on a 9x9 board.
     */
    public static function koPosition(): array
    {
        // Classic ko pattern:
        // .BW.....
        // B.BW....
        // .BW.....
        // This gives black a ko fight at column 1, row 1
        $board = static::emptyBoard(9);
        $s = 9;
        $board[0 * $s + 1] = BoardService::BLACK;
        $board[0 * $s + 2] = BoardService::WHITE;
        $board[1 * $s + 0] = BoardService::BLACK;
        $board[1 * $s + 2] = BoardService::BLACK;
        $board[1 * $s + 3] = BoardService::WHITE;
        $board[2 * $s + 1] = BoardService::BLACK;
        $board[2 * $s + 2] = BoardService::WHITE;

        return $board;
    }
}
