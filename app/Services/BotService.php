<?php

namespace App\Services;

use App\Models\Game;
use App\Services\GoEngine\BoardService;
use App\Services\GoEngine\GoRuleException;

/**
 * BotService — AI สำหรับบอทเล่นหมากล้อม
 *
 * ใช้สามเทคนิคประกอบกัน:
 *  1. Heuristics     — ให้คะแนนแต่ละตาตามกฎง่าย ๆ (จับหมาก, ป้องกัน, รูปร่าง ฯลฯ)
 *  2. Flat MC Search — สุ่มเล่นหลายรอบจากตาที่คัดมา แล้วเฉลี่ยผลลัพธ์
 *  3. Position Eval  — ประเมินคะแนนบนกระดานอย่างรวดเร็ว (นับหมาก + territory)
 *
 * แต่ละระดับจะใช้ความลึกและจำนวน rollout ต่างกัน:
 *  8k  — Heuristics เท่านั้น + noise สูง (เล่นผิดบ้าง)
 *  5k  — Heuristics + noise ต่ำ
 *  2k  — Heuristics + 30 rollouts × top 10 candidates
 *  1d  — Heuristics + 60 rollouts × top 15 candidates
 *  3d  — Heuristics + 100 rollouts × top 20 candidates
 */
class BotService
{
    public function __construct(private BoardService $board) {}

    // ──────────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * คืน coordinate string (เช่น "D4") หรือ null เพื่อ pass
     */
    public function getBotMove(Game $game, string $color): ?string
    {
        $board    = $game->board_state;
        $size     = $game->board_size;
        $colorInt = ($color === 'black') ? BoardService::BLACK : BoardService::WHITE;
        $koPoint  = $game->ko_point;
        $level    = $this->getBotLevel($game, $color);

        $candidates = $this->getLegalMoves($board, $size, $colorInt, $koPoint);

        if (empty($candidates)) {
            return null; // pass
        }

        // ── คัดตาที่ดีที่สุดด้วย heuristics ──────────────────────────────────
        $scored = $this->scoreCandidates($board, $size, $colorInt, $koPoint, $candidates, $level);

        // ── MCTS rollout สำหรับระดับ 2k ขึ้นไป ─────────────────────────────
        [$topN, $rollouts, $rolloutDepth] = $this->mctsParams($level, $size);

        if ($rollouts > 0 && $topN > 0) {
            $top = array_slice($scored, 0, min($topN, count($scored)));
            foreach ($top as &$entry) {
                $mcScore = $this->flatMonteCarlo(
                    $board, $size, $colorInt, $koPoint,
                    $entry['row'], $entry['col'],
                    $rollouts, $rolloutDepth
                );
                // ผสม heuristic 40% + MC 60%
                $entry['score'] = $entry['score'] * 0.4 + $mcScore * 0.6;
            }
            unset($entry);
            usort($top, fn ($a, $b) => $b['score'] <=> $a['score']);
            $best = $top[0];
        } else {
            $best = $scored[0];
        }

        // ── ตรวจสอบว่าควร pass หรือเล่น ──────────────────────────────────────
        if ($this->shouldPass($board, $size, $colorInt, $game->move_number, $best['score'])) {
            return null;
        }

        return $this->board->indexToCoordinate($best['row'], $best['col'], $size);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Heuristic scoring
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ให้คะแนนตาผู้เล่น color ที่ (row,col) โดยใช้ heuristics
     * คืนค่าเป็น float  (ยิ่งสูงยิ่งดี)
     */
    private function scoreMove(
        array $board, int $size, int $row, int $col,
        int $color, ?string $koPoint
    ): float {
        $score = 0.0;
        $opponent = ($color === BoardService::BLACK) ? BoardService::WHITE : BoardService::BLACK;
        $idx = $row * $size + $col;

        // ── ลอง place stone เพื่อดูผล ─────────────────────────────────────────
        try {
            $result = $this->board->placeStone($board, $size, $row, $col, $color, $koPoint);
        } catch (GoRuleException) {
            return -9999.0; // illegal
        }

        $newBoard = $result->newBoard;
        $captures = count($result->capturedStones);

        // ── Immediate captures ─────────────────────────────────────────────────
        $score += $captures * 20.0;

        // ── Self-atari penalty ─────────────────────────────────────────────────
        $ownGroup = $this->board->getGroup($newBoard, $size, $idx);
        $ownLiberties = count($ownGroup['liberties']);
        if ($ownLiberties === 1) {
            $score -= 25.0; // หมากของเราถูกคุกคามหลังวาง
        }

        // ── Puts opponent in atari (1 liberty) ────────────────────────────────
        $score += $this->countAtariThreats($newBoard, $size, $opponent) * 12.0;

        // ── Saves own group in atari ───────────────────────────────────────────
        $ownAtariBefore = $this->countAtariThreats($board, $size, $color);
        $ownAtariAfter  = $this->countAtariThreats($newBoard, $size, $color);
        if ($ownAtariBefore > $ownAtariAfter) {
            $score += ($ownAtariBefore - $ownAtariAfter) * 15.0;
        }

        // ── Connection bonus: ต่อกลุ่มตัวเอง ──────────────────────────────────
        $ownNeighborGroups = $this->countNeighborGroups($board, $size, $idx, $color);
        if ($ownNeighborGroups >= 2) {
            $score += 8.0;
        } elseif ($ownNeighborGroups === 1) {
            $score += 3.0;
        }

        // ── Separation: ตัดกลุ่มคู่ต่อสู้ ─────────────────────────────────────
        $oppNeighborGroups = $this->countNeighborGroups($board, $size, $idx, $opponent);
        if ($oppNeighborGroups >= 2) {
            $score += 6.0;
        }

        // ── Centrality bonus: ตาบริเวณกลางกระดานดีกว่าขอบ ─────────────────────
        $center = ($size - 1) / 2.0;
        $dist   = abs($row - $center) + abs($col - $center);
        $maxDist = $center * 2;
        $score  += (1.0 - $dist / $maxDist) * 5.0;

        // ── Star point bonus (early game) ──────────────────────────────────────
        if ($this->isStarPoint($row, $col, $size)) {
            $score += 4.0;
        }

        // ── Third-line bonus: ────────────────────────────────────────────────
        // แถวที่ 3 หรือ 4 จากขอบดีสำหรับ territory (index 2 หรือ 3)
        $distFromEdge = min($row, $col, $size - 1 - $row, $size - 1 - $col);
        if ($distFromEdge === 2 || $distFromEdge === 3) {
            $score += 2.0;
        }
        if ($distFromEdge === 0 || $distFromEdge === 1) {
            $score -= 3.0; // ขอบกระดานไม่ดีในเกมเปิด
        }

        return $score;
    }

    /**
     * ให้คะแนนตาที่เป็นไปได้ทั้งหมด เรียงจากดีที่สุด
     * noise จะถูกเพิ่มตามระดับของบอท
     */
    private function scoreCandidates(
        array $board, int $size, int $color, ?string $koPoint,
        array $candidates, string $level
    ): array {
        $noise = $this->noiseRange($level);
        $scored = [];

        foreach ($candidates as [$row, $col]) {
            $s = $this->scoreMove($board, $size, $row, $col, $color, $koPoint);
            if ($s <= -9000) {
                continue;
            }
            if ($noise > 0) {
                $s += (mt_rand(-$noise * 10, $noise * 10) / 10.0);
            }
            $scored[] = ['row' => $row, 'col' => $col, 'score' => $s];
        }

        if (empty($scored)) {
            // fallback: เลือก random
            [$r, $c] = $candidates[array_rand($candidates)];
            return [['row' => $r, 'col' => $c, 'score' => 0.0]];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $scored;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Flat Monte Carlo Search
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * สุ่มเล่นจากตา (row,col) จำนวน $rollouts รอบ
     * คืนค่า win rate (0.0-1.0) จากมุมมองของ $color
     */
    private function flatMonteCarlo(
        array $board, int $size, int $color, ?string $koPoint,
        int $row, int $col, int $rollouts, int $depth
    ): float {
        try {
            $result = $this->board->placeStone($board, $size, $row, $col, $color, $koPoint);
        } catch (GoRuleException) {
            return 0.0;
        }

        $boardAfter = $result->newBoard;
        $nextColor  = ($color === BoardService::BLACK) ? BoardService::WHITE : BoardService::BLACK;

        $wins = 0;
        for ($i = 0; $i < $rollouts; $i++) {
            $score = $this->randomRollout($boardAfter, $size, $nextColor, $depth);
            // score > 0 หมายความว่า BLACK ได้เปรียบ
            if ($color === BoardService::BLACK) {
                if ($score > 0) {
                    $wins++;
                }
            } else {
                if ($score < 0) {
                    $wins++;
                }
            }
        }

        return $wins / $rollouts;
    }

    /**
     * สุ่มเล่นไปจนครบ depth ตา แล้วประเมินกระดาน
     * คืนค่า score > 0 หมายถึง black ได้เปรียบ, < 0 หมายถึง white ได้เปรียบ
     */
    private function randomRollout(array $board, int $size, int $startColor, int $depth): float
    {
        $color       = $startColor;
        $passes      = 0;
        $allIndices  = range(0, $size * $size - 1);

        for ($i = 0; $i < $depth; $i++) {
            // หา empty cells
            $empties = array_filter($allIndices, fn ($idx) => $board[$idx] === BoardService::EMPTY);

            if (empty($empties)) {
                break;
            }

            // สุ่มลำดับเพื่อหาตาที่ถูกกฎหมาย
            $shuffled = $empties;
            shuffle($shuffled);
            $moved = false;

            // ลองแค่ 8 ตาสุ่ม เพื่อประสิทธิภาพ
            foreach (array_slice($shuffled, 0, 8) as $idx) {
                $r = intdiv($idx, $size);
                $c = $idx % $size;
                try {
                    $result = $this->board->placeStone($board, $size, $r, $c, $color, null);
                    $board  = $result->newBoard;
                    $moved  = true;
                    $passes = 0;
                    break;
                } catch (GoRuleException) {
                    // ตาไม่ถูกกฎหมาย ลองตาต่อไป
                }
            }

            if (! $moved) {
                $passes++;
                if ($passes >= 2) {
                    break;
                }
            }

            $color = ($color === BoardService::BLACK) ? BoardService::WHITE : BoardService::BLACK;
        }

        return $this->quickEvaluate($board, $size);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Position Evaluation
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * ประเมินกระดานอย่างรวดเร็ว: (หมากดำ + territory ดำ) - (หมากขาว + territory ขาว)
     * ค่าบวก = ดำได้เปรียบ, ค่าลบ = ขาวได้เปรียบ
     */
    private function quickEvaluate(array $board, int $size): float
    {
        $black = 0;
        $white = 0;

        // นับหมาก
        foreach ($board as $v) {
            if ($v === BoardService::BLACK) {
                $black++;
            } elseif ($v === BoardService::WHITE) {
                $white++;
            }
        }

        // ประเมิน territory อย่างคร่าว ๆ ด้วย flood fill
        $visited = array_fill(0, $size * $size, false);

        for ($i = 0; $i < $size * $size; $i++) {
            if ($board[$i] !== BoardService::EMPTY || $visited[$i]) {
                continue;
            }

            $region          = [];
            $borderingColors = [];
            $stack           = [$i];

            while (! empty($stack)) {
                $idx = array_pop($stack);
                if ($visited[$idx]) {
                    continue;
                }
                $visited[$idx] = true;

                if ($board[$idx] === BoardService::EMPTY) {
                    $region[] = $idx;
                    foreach ($this->neighbors($idx, $size) as $n) {
                        if (! $visited[$n]) {
                            if ($board[$n] === BoardService::EMPTY) {
                                $stack[] = $n;
                            } else {
                                $borderingColors[$board[$n]] = true;
                            }
                        }
                    }
                }
            }

            $borderingBlack = isset($borderingColors[BoardService::BLACK]);
            $borderingWhite = isset($borderingColors[BoardService::WHITE]);

            if ($borderingBlack && ! $borderingWhite) {
                $black += count($region);
            } elseif ($borderingWhite && ! $borderingBlack) {
                $white += count($region);
            }
        }

        return (float) ($black - $white);
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Helper Methods
    // ──────────────────────────────────────────────────────────────────────────

    /** คืนรายการตาทั้งหมดที่ถูกกฎหมายสำหรับ color */
    private function getLegalMoves(array $board, int $size, int $color, ?string $koPoint): array
    {
        $moves = [];
        for ($r = 0; $r < $size; $r++) {
            for ($c = 0; $c < $size; $c++) {
                if ($board[$r * $size + $c] === BoardService::EMPTY) {
                    if ($this->board->isLegalMove($board, $size, $r, $c, $color, $koPoint)) {
                        $moves[] = [$r, $c];
                    }
                }
            }
        }

        return $moves;
    }

    /** นับกลุ่มของ targetColor ที่อยู่ในภาวะ atari (1 liberty) */
    private function countAtariThreats(array $board, int $size, int $targetColor): int
    {
        $visitedGroups = [];
        $atariCount    = 0;

        for ($i = 0; $i < $size * $size; $i++) {
            if ($board[$i] !== $targetColor) {
                continue;
            }
            if (isset($visitedGroups[$i])) {
                continue;
            }
            $group = $this->board->getGroup($board, $size, $i);
            foreach ($group['stones'] as $s) {
                $visitedGroups[$s] = true;
            }
            if (count($group['liberties']) === 1) {
                $atariCount++;
            }
        }

        return $atariCount;
    }

    /** นับจำนวนกลุ่มของ targetColor ที่เชื่อมต่อกับ idx */
    private function countNeighborGroups(array $board, int $size, int $idx, int $targetColor): int
    {
        $seenGroupRoots = [];
        foreach ($this->neighbors($idx, $size) as $n) {
            if ($board[$n] === $targetColor) {
                $group = $this->board->getGroup($board, $size, $n);
                $root  = min($group['stones']);
                $seenGroupRoots[$root] = true;
            }
        }

        return count($seenGroupRoots);
    }

    /** ตรวจสอบว่า (row,col) เป็น star point สำหรับขนาดกระดานนี้หรือไม่ */
    private function isStarPoint(int $row, int $col, int $size): bool
    {
        $pts = match ($size) {
            19 => [[3,3],[3,9],[3,15],[9,3],[9,9],[9,15],[15,3],[15,9],[15,15]],
            13 => [[3,3],[3,6],[3,9],[6,3],[6,6],[6,9],[9,3],[9,6],[9,9]],
            9  => [[2,2],[2,4],[2,6],[4,2],[4,4],[4,6],[6,2],[6,4],[6,6]],
            default => [],
        };

        return in_array([$row, $col], $pts, true);
    }

    /** คืน indices ที่อยู่ติดกัน (4 ทิศ) */
    private function neighbors(int $idx, int $size): array
    {
        $row = intdiv($idx, $size);
        $col = $idx % $size;
        $ns  = [];
        if ($row > 0) {
            $ns[] = ($row - 1) * $size + $col;
        }
        if ($row < $size - 1) {
            $ns[] = ($row + 1) * $size + $col;
        }
        if ($col > 0) {
            $ns[] = $row * $size + ($col - 1);
        }
        if ($col < $size - 1) {
            $ns[] = $row * $size + ($col + 1);
        }

        return $ns;
    }

    // ──────────────────────────────────────────────────────────────────────────
    //  Level Configuration
    // ──────────────────────────────────────────────────────────────────────────

    private function getBotLevel(Game $game, string $color): string
    {
        $botUser = ($color === 'black') ? $game->blackPlayer : $game->whitePlayer;

        return $botUser->bot_level ?? '8k';
    }

    /**
     * คืน noise range สำหรับแต่ละระดับ
     * ค่าสูง = บอทเล่นผิดพลาดบ่อยขึ้น
     */
    private function noiseRange(string $level): float
    {
        return match ($level) {
            '8k'    => 25.0,
            '5k'    => 12.0,
            '2k'    => 6.0,
            '1d'    => 3.0,
            '3d'    => 1.5,
            default => 20.0,
        };
    }

    /**
     * คืน [topN candidates, rollouts per candidate, rollout depth]
     * ระดับต่ำกว่า 2k ไม่ใช้ MCTS (rollouts = 0)
     */
    private function mctsParams(string $level, int $boardSize): array
    {
        // rollout depth ขึ้นอยู่กับขนาดกระดาน
        $depth = match ($boardSize) {
            9  => 15,
            13 => 20,
            default => 30,
        };

        return match ($level) {
            '2k'    => [10, 30, $depth],
            '1d'    => [15, 60, $depth],
            '3d'    => [20, 100, $depth],
            default => [0, 0, 0], // 8k, 5k ไม่ใช้ MCTS
        };
    }

    /**
     * พิจารณาว่าบอทควร pass หรือไม่
     * pass เมื่อ: เกมผ่านมาหลาย ตา AND ตาที่ดีที่สุดมีคะแนน MC ต่ำมาก
     */
    private function shouldPass(array $board, int $size, int $color, int $moveNumber, float $bestScore): bool
    {
        // pass ก็ต่อเมื่อเกมไปได้ไกลพอสมควรแล้ว และ bestScore ไม่ดีมากนัก
        $minMoves = $size * $size * 0.4; // ประมาณ 40% ของกระดาน
        if ($moveNumber < $minMoves) {
            return false;
        }

        // ถ้า bestScore เป็น win rate จาก MC และต่ำกว่า 0.35 แสดงว่าเราเสียเปรียบมาก
        // แต่บอทยังไม่ยอม pass ง่าย ๆ — ควบคุมโดยระดับ
        return $bestScore < 0.3 && $moveNumber > $minMoves * 1.5;
    }
}
