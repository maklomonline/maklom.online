<?php

namespace App\Services\GoEngine;

class BoardService
{
    public const EMPTY = 0;
    public const BLACK = 1;
    public const WHITE = 2;

    // Standard handicap points indexed by board size and handicap count
    private const HANDICAP_POINTS = [
        19 => [
            2 => [[3, 15], [15, 3]],
            3 => [[3, 15], [15, 3], [15, 15]],
            4 => [[3, 3], [3, 15], [15, 3], [15, 15]],
            5 => [[3, 3], [3, 15], [15, 3], [15, 15], [9, 9]],
            6 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9]],
            7 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 9]],
            8 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15]],
            9 => [[3, 3], [3, 15], [15, 3], [15, 15], [3, 9], [15, 9], [9, 3], [9, 15], [9, 9]],
        ],
        13 => [
            2 => [[3, 9], [9, 3]],
            3 => [[3, 9], [9, 3], [9, 9]],
            4 => [[3, 3], [3, 9], [9, 3], [9, 9]],
            5 => [[3, 3], [3, 9], [9, 3], [9, 9], [6, 6]],
        ],
        9 => [
            2 => [[2, 6], [6, 2]],
            3 => [[2, 6], [6, 2], [6, 6]],
            4 => [[2, 2], [2, 6], [6, 2], [6, 6]],
        ],
    ];

    public function createEmptyBoard(int $size): array
    {
        return array_fill(0, $size * $size, self::EMPTY);
    }

    public function applyHandicap(array $board, int $size, int $handicap): array
    {
        if ($handicap < 2 || ! isset(self::HANDICAP_POINTS[$size][$handicap])) {
            return $board;
        }
        foreach (self::HANDICAP_POINTS[$size][$handicap] as [$row, $col]) {
            $board[$row * $size + $col] = self::BLACK;
        }

        return $board;
    }

    /**
     * Place a stone and return the resulting board state.
     *
     * @throws GoRuleException
     */
    public function placeStone(array $board, int $size, int $row, int $col, int $color, ?string $koPoint): PlaceMoveResult
    {
        if ($row < 0 || $row >= $size || $col < 0 || $col >= $size) {
            throw GoRuleException::outOfBounds();
        }

        $idx = $row * $size + $col;

        if ($board[$idx] !== self::EMPTY) {
            throw GoRuleException::occupied();
        }

        // Ko check
        $coord = $this->indexToCoordinate($row, $col, $size);
        if ($koPoint !== null && $coord === $koPoint) {
            throw GoRuleException::ko();
        }

        // Tentatively place the stone
        $board[$idx] = $color;
        $opponent = ($color === self::BLACK) ? self::WHITE : self::BLACK;

        // Remove captured opponent groups
        $capturedStones = [];
        foreach ($this->getNeighbors($idx, $size) as $neighborIdx) {
            if ($board[$neighborIdx] === $opponent) {
                $group = $this->getGroup($board, $size, $neighborIdx);
                if (count($group['liberties']) === 0) {
                    foreach ($group['stones'] as $stoneIdx) {
                        $capturedStones[] = $this->idxToRowCol($stoneIdx, $size);
                        $board[$stoneIdx] = self::EMPTY;
                    }
                }
            }
        }

        // Suicide check: if own group has no liberties after captures, it's illegal
        $ownGroup = $this->getGroup($board, $size, $idx);
        if (count($ownGroup['liberties']) === 0) {
            throw GoRuleException::suicide();
        }

        // Determine new ko point
        $newKoPoint = null;
        if (count($capturedStones) === 1 && count($ownGroup['stones']) === 1 && count($ownGroup['liberties']) === 1) {
            $newKoPoint = $this->indexToCoordinate($capturedStones[0][0], $capturedStones[0][1], $size);
        }

        return new PlaceMoveResult($board, $capturedStones, $newKoPoint);
    }

    /**
     * BFS to find a group and its liberties.
     */
    public function getGroup(array $board, int $size, int $startIdx): array
    {
        $color = $board[$startIdx];
        if ($color === self::EMPTY) {
            return ['stones' => [], 'liberties' => []];
        }

        $visited = [];
        $stones = [];
        $liberties = [];
        $stack = [$startIdx];

        while (! empty($stack)) {
            $idx = array_pop($stack);
            if (isset($visited[$idx])) {
                continue;
            }
            $visited[$idx] = true;

            if ($board[$idx] === $color) {
                $stones[] = $idx;
                foreach ($this->getNeighbors($idx, $size) as $n) {
                    if (! isset($visited[$n])) {
                        if ($board[$n] === self::EMPTY) {
                            $visited[$n] = true; // mark liberty as seen to avoid duplicates
                            $liberties[] = $n;
                        } elseif ($board[$n] === $color) {
                            $stack[] = $n;
                        }
                    }
                }
            }
        }

        return ['stones' => $stones, 'liberties' => array_unique($liberties)];
    }

    /**
     * Calculate score using Chinese rules.
     */
    public function calculateScore(array $board, int $size, array $deadStones, int $capturesBlack, int $capturesWhite, float $komi): ScoreResult
    {
        // Remove dead stones from board
        foreach ($deadStones as [$row, $col]) {
            $idx = $row * $size + $col;
            if ($board[$idx] === self::BLACK) {
                $capturesWhite++;
            } elseif ($board[$idx] === self::WHITE) {
                $capturesBlack++;
            }
            $board[$idx] = self::EMPTY;
        }

        // Flood-fill territory
        $blackTerritory = 0;
        $whiteTerritory = 0;
        $visited = array_fill(0, $size * $size, false);

        for ($i = 0; $i < $size * $size; $i++) {
            if ($board[$i] !== self::EMPTY || $visited[$i]) {
                continue;
            }

            // BFS this empty region
            $region = [];
            $borderingColors = [];
            $stack = [$i];

            while (! empty($stack)) {
                $idx = array_pop($stack);
                if ($visited[$idx]) {
                    continue;
                }
                $visited[$idx] = true;

                if ($board[$idx] === self::EMPTY) {
                    $region[] = $idx;
                    foreach ($this->getNeighbors($idx, $size) as $n) {
                        if (! $visited[$n]) {
                            if ($board[$n] === self::EMPTY) {
                                $stack[] = $n;
                            } else {
                                $borderingColors[$board[$n]] = true;
                            }
                        }
                    }
                }
            }

            $borderingBlack = isset($borderingColors[self::BLACK]);
            $borderingWhite = isset($borderingColors[self::WHITE]);

            if ($borderingBlack && ! $borderingWhite) {
                $blackTerritory += count($region);
            } elseif ($borderingWhite && ! $borderingBlack) {
                $whiteTerritory += count($region);
            }
            // dame (touches both) counts for neither
        }

        // Chinese rules: count live stones on board too
        for ($i = 0; $i < $size * $size; $i++) {
            if ($board[$i] === self::BLACK) {
                $blackTerritory++;
            } elseif ($board[$i] === self::WHITE) {
                $whiteTerritory++;
            }
        }

        return new ScoreResult($blackTerritory, $whiteTerritory, $capturesBlack, $capturesWhite, $komi);
    }

    public function coordinateToRowCol(string $coord, int $size): array
    {
        $coord = strtoupper(trim($coord));
        $col = ord($coord[0]) - ord('A');
        if ($col >= 8) {
            $col--; // skip 'I'
        }
        $row = $size - (int) substr($coord, 1);

        return [$row, $col];
    }

    public function indexToCoordinate(int $row, int $col, int $size): string
    {
        $colLetter = $col >= 8 ? chr(ord('A') + $col + 1) : chr(ord('A') + $col);
        $rowNumber = $size - $row;

        return $colLetter.$rowNumber;
    }

    public function isLegalMove(array $board, int $size, int $row, int $col, int $color, ?string $koPoint): bool
    {
        try {
            $this->placeStone($board, $size, $row, $col, $color, $koPoint);

            return true;
        } catch (GoRuleException) {
            return false;
        }
    }

    private function getNeighbors(int $idx, int $size): array
    {
        $row = intdiv($idx, $size);
        $col = $idx % $size;
        $neighbors = [];

        if ($row > 0) {
            $neighbors[] = ($row - 1) * $size + $col;
        }
        if ($row < $size - 1) {
            $neighbors[] = ($row + 1) * $size + $col;
        }
        if ($col > 0) {
            $neighbors[] = $row * $size + ($col - 1);
        }
        if ($col < $size - 1) {
            $neighbors[] = $row * $size + ($col + 1);
        }

        return $neighbors;
    }

    private function idxToRowCol(int $idx, int $size): array
    {
        return [intdiv($idx, $size), $idx % $size];
    }
}
