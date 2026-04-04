<?php

namespace App\Services;

use App\Models\Game;
use App\Services\GoEngine\BoardService;

class SgfService
{
    // Column letters used in our coordinate system (no I)
    private const COL_LETTERS = 'ABCDEFGHJKLMNOPQRST';

    public function __construct(private BoardService $boardService) {}

    /**
     * Generate a base SGF string from the game's move history.
     */
    public function generateSgf(Game $game): string
    {
        $moves = $game->moves()->orderBy('move_number')->get();

        $black = addslashes($game->blackPlayer?->getDisplayName() ?? '?');
        $white = addslashes($game->whitePlayer?->getDisplayName() ?? '?');

        $sgf = "(;FF[4]GM[1]SZ[{$game->board_size}]";
        $sgf .= "KM[{$game->komi}]";
        $sgf .= "PB[{$black}]PW[{$white}]";
        if ($game->result) {
            $sgf .= 'RE[' . addslashes($game->result) . ']';
        }
        if ($game->handicap >= 2) {
            $sgf .= "HA[{$game->handicap}]";
        }

        foreach ($moves as $move) {
            $color = $move->color === 'black' ? 'B' : 'W';
            if ($move->coordinate === null) {
                $sgf .= ";{$color}[]"; // pass
            } else {
                $sgfCoord = $this->coordToSgf($move->coordinate, $game->board_size);
                $sgf .= ";{$color}[{$sgfCoord}]";
            }
        }

        $sgf .= ')';

        return $sgf;
    }

    /**
     * Reconstruct board state at every move position.
     * Returns an array indexed 0..N where index 0 = initial position,
     * index N = position after move N.
     */
    public function computeBoardStates(Game $game): array
    {
        $bs = $game->board_size;
        $board = $this->boardService->createEmptyBoard($bs);

        if ($game->handicap >= 2) {
            $board = $this->boardService->applyHandicap($board, $bs, $game->handicap);
        }

        $states = [$board];
        $koPoint = null;

        foreach ($game->moves()->orderBy('move_number')->get() as $move) {
            if ($move->coordinate === null) {
                // Pass — board unchanged, ko lifted
                $koPoint = null;
                $states[] = $board;
                continue;
            }

            $col = strpos(self::COL_LETTERS, strtoupper($move->coordinate[0]));
            $row = $bs - (int) substr($move->coordinate, 1);
            $color = $move->color === 'black' ? BoardService::BLACK : BoardService::WHITE;

            try {
                $result = $this->boardService->placeStone($board, $bs, $row, $col, $color, $koPoint);
                $board = $result->newBoard;
                $koPoint = $result->newKoPoint;
            } catch (\Throwable) {
                // Invalid move in history — keep board as-is to avoid crashing
            }

            $states[] = $board;
        }

        return $states;
    }

    /**
     * Convert our coordinate (e.g. "A19", "T1") to SGF format (e.g. "aa", "ss").
     * SGF column = left→right (a–s), row = top→bottom (a–s).
     */
    public function coordToSgf(string $coord, int $boardSize): string
    {
        $col = strpos(self::COL_LETTERS, strtoupper($coord[0]));
        $row = $boardSize - (int) substr($coord, 1); // 0 = top row
        return chr(ord('a') + $col) . chr(ord('a') + $row);
    }

    /**
     * Convert SGF coordinate (e.g. "pd") to our format (e.g. "Q16") for a given board size.
     */
    public function sgfToCoord(string $sgfCoord, int $boardSize): ?string
    {
        if (strlen($sgfCoord) !== 2) {
            return null; // pass
        }
        $col = ord($sgfCoord[0]) - ord('a');
        $row = ord($sgfCoord[1]) - ord('a');
        $rowNumber = $boardSize - $row;
        return self::COL_LETTERS[$col] . $rowNumber;
    }
}
