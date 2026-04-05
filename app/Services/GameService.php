<?php

namespace App\Services;

use App\Events\DeadStonesUpdated;
use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\MoveMade;
use App\Events\PlayerPassed;
use App\Events\PlayerResigned;
use App\Events\ScoreConfirmationUpdated;
use App\Events\ScoringCancelled;
use App\Events\ScoringPhaseStarted;
use App\Models\Game;
use App\Models\GameMove;
use App\Models\GameRoom;
use App\Models\User;
use App\Models\UserStat;
use App\Services\GoEngine\BoardService;
use App\Services\GoEngine\GoRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\RatingService;

class GameService
{
    public function __construct(
        private BoardService $board,
        private ClockService $clock,
    ) {}

    public function createGame(GameRoom $room, User $black, User $white): Game
    {
        $boardState = $this->board->createEmptyBoard($room->board_size);

        if ($room->handicap >= 2) {
            $boardState = $this->board->applyHandicap($boardState, $room->board_size, $room->handicap);
        }

        $game = Game::create([
            'room_id' => $room->id,
            'black_player_id' => $black->id,
            'white_player_id' => $white->id,
            'board_size' => $room->board_size,
            'komi' => $room->komi,
            'handicap' => $room->handicap,
            'clock_type' => $room->clock_type,
            'main_time' => $room->main_time,
            'byoyomi_periods' => $room->byoyomi_periods,
            'byoyomi_seconds' => $room->byoyomi_seconds,
            'fischer_increment' => $room->fischer_increment,
            'black_time_left' => $room->main_time,
            'white_time_left' => $room->main_time,
            'black_periods_left' => $room->byoyomi_periods,
            'white_periods_left' => $room->byoyomi_periods,
            'current_color' => ($room->handicap >= 2) ? 'white' : 'black',
            'board_state' => $boardState,
            'status' => 'active',
            'started_at' => now(),
        ]);

        $room->update(['status' => 'playing']);

        broadcast(new GameStarted($game))->toOthers();

        return $game;
    }

    /**
     * @throws GoRuleException
     * @throws ClockTimeoutException
     */
    public function makeMove(Game $game, User $user, string $coordinate): Game
    {
        if (! $game->isActive()) {
            throw GoRuleException::gameNotActive();
        }
        if (! $game->isPlayerTurn($user)) {
            throw GoRuleException::notYourTurn();
        }

        $color = $game->getPlayerColor($user);
        $secondsSpent = $this->computeSecondsSpent($game);

        // Clock check
        $clockData = $this->clock->recordMove($game, $color, $secondsSpent);

        [$row, $col] = $this->board->coordinateToRowCol($coordinate, $game->board_size);
        $colorInt = ($color === 'black') ? BoardService::BLACK : BoardService::WHITE;

        $result = $this->board->placeStone(
            $game->board_state,
            $game->board_size,
            $row, $col,
            $colorInt,
            $game->ko_point
        );

        $moveNumber = $game->move_number + 1;
        $capturesKey = "captures_{$color}";
        $newCaptures = $game->{$capturesKey} + count($result->capturedStones);
        $nextColor = ($color === 'black') ? 'white' : 'black';

        DB::transaction(function () use ($game, $result, $moveNumber, $coordinate, $color, $newCaptures, $nextColor, $clockData, $secondsSpent, $capturesKey) {
            GameMove::create([
                'game_id' => $game->id,
                'move_number' => $moveNumber,
                'color' => $color,
                'coordinate' => $coordinate,
                'captured_stones' => $result->capturedStones,
                'time_spent' => $secondsSpent,
                'time_left_after' => $clockData["{$color}_time_left"],
                'periods_left_after' => $clockData["{$color}_periods_left"] ?? null,
            ]);

            $game->update(array_merge([
                'board_state' => $result->newBoard,
                'ko_point' => $result->newKoPoint,
                'move_number' => $moveNumber,
                $capturesKey => $newCaptures,
                'current_color' => $nextColor,
                'consecutive_passes' => 0,
                'last_move_at' => Carbon::now(),
            ], $clockData));
        });

        $game->refresh();
        broadcast(new MoveMade($game, $coordinate, $color, $result->capturedStones))->toOthers();

        return $game;
    }

    public function passMove(Game $game, User $user): Game
    {
        if (! $game->isActive()) {
            throw GoRuleException::gameNotActive();
        }
        if (! $game->isPlayerTurn($user)) {
            throw GoRuleException::notYourTurn();
        }

        $color = $game->getPlayerColor($user);
        $secondsSpent = $this->computeSecondsSpent($game);
        $clockData = $this->clock->recordMove($game, $color, $secondsSpent);

        $nextColor = ($color === 'black') ? 'white' : 'black';
        $newPasses = $game->consecutive_passes + 1;

        $moveNumber = $game->move_number + 1;
        GameMove::create([
            'game_id' => $game->id,
            'move_number' => $moveNumber,
            'color' => $color,
            'coordinate' => null,
            'time_spent' => $secondsSpent,
            'time_left_after' => $clockData["{$color}_time_left"],
            'periods_left_after' => $clockData["{$color}_periods_left"] ?? null,
        ]);

        $game->update(array_merge([
            'ko_point' => null,
            'move_number' => $moveNumber,
            'current_color' => $nextColor,
            'consecutive_passes' => $newPasses,
            'last_move_at' => Carbon::now(),
        ], $clockData));

        $game->refresh();
        broadcast(new PlayerPassed($game, $color))->toOthers();

        if ($newPasses >= 2) {
            $this->enterScoring($game);
        }

        return $game;
    }

    public function resignGame(Game $game, User $user): Game
    {
        if (! $game->isActive()) {
            throw GoRuleException::gameNotActive();
        }

        $color = $game->getPlayerColor($user);
        $opponent = $game->getOpponent($user);
        $result = ($color === 'black') ? 'W+R' : 'B+R';

        broadcast(new PlayerResigned($game, $color))->toOthers();

        return $this->finishGame($game, $result, $opponent, 'resign');
    }

    public function enterScoring(Game $game): void
    {
        $game->update([
            'status' => 'scoring',
            'consecutive_passes' => 0,
            'dead_stones' => [],
            'score_confirmed_black' => false,
            'score_confirmed_white' => false,
        ]);
        $game->refresh();
        broadcast(new ScoringPhaseStarted($game))->toOthers();
    }

    /**
     * Toggle a connected group of stones as dead/alive.
     * Any change resets both players' confirmations.
     */
    public function toggleDeadGroup(Game $game, string $coordinate): Game
    {
        if ($game->status !== 'scoring') {
            return $game;
        }

        [$row, $col] = $this->board->coordinateToRowCol($coordinate, $game->board_size);
        $idx = $row * $game->board_size + $col;

        if ($game->board_state[$idx] === BoardService::EMPTY) {
            return $game;
        }

        // Find the connected group
        $group = $this->board->getGroup($game->board_state, $game->board_size, $idx);
        $groupCoords = array_map(
            fn ($i) => [intdiv($i, $game->board_size), $i % $game->board_size],
            $group['stones']
        );

        // Current dead stones as set of "row,col" strings
        $currentDead = collect($game->dead_stones ?? [])
            ->map(fn ($s) => "{$s[0]},{$s[1]}")
            ->flip()
            ->all();

        // Check if any stone in the group is already dead
        $anyDead = false;
        foreach ($groupCoords as [$r, $c]) {
            if (isset($currentDead["{$r},{$c}"])) {
                $anyDead = true;
                break;
            }
        }

        if ($anyDead) {
            // Remove entire group from dead stones (mark alive)
            $groupSet = collect($groupCoords)->map(fn ($s) => "{$s[0]},{$s[1]}")->flip()->all();
            $newDead = collect($game->dead_stones ?? [])
                ->filter(fn ($s) => ! isset($groupSet["{$s[0]},{$s[1]}"]))
                ->values()
                ->all();
        } else {
            // Add entire group to dead stones
            $newDead = array_merge($game->dead_stones ?? [], $groupCoords);
        }

        $game->update([
            'dead_stones' => $newDead,
            'score_confirmed_black' => false,
            'score_confirmed_white' => false,
        ]);
        $game->refresh();

        broadcast(new DeadStonesUpdated($game));

        return $game;
    }

    /**
     * Mark a player's confirmation. Finishes the game only when both players confirm.
     * Returns ['finished' => bool, 'result' => string|null].
     */
    public function confirmScore(Game $game, User $user): array
    {
        if ($game->status !== 'scoring') {
            throw GoRuleException::gameNotActive();
        }

        $color = $game->getPlayerColor($user);
        if ($color === 'black') {
            $game->update(['score_confirmed_black' => true]);
        } else {
            $game->update(['score_confirmed_white' => true]);
        }
        $game->refresh();

        broadcast(new ScoreConfirmationUpdated($game));

        if ($game->score_confirmed_black && $game->score_confirmed_white) {
            $scoreResult = $this->board->calculateScore(
                $game->board_state,
                $game->board_size,
                $game->dead_stones ?? [],
                $game->captures_black,
                $game->captures_white,
                $game->komi
            );

            $winner = match ($scoreResult->winner) {
                'black' => $game->blackPlayer,
                'white' => $game->whitePlayer,
                default => null,
            };

            $game = $this->finishGame($game, $scoreResult->result, $winner, 'score');

            return ['finished' => true, 'result' => $game->result];
        }

        return ['finished' => false, 'result' => null];
    }

    /**
     * Bot-only: confirm score unconditionally (skips two-player requirement).
     */
    public function confirmScoreForBot(Game $game, User $botUser): void
    {
        if ($game->status !== 'scoring') {
            return;
        }

        $color = $game->getPlayerColor($botUser);
        if ($color === 'black') {
            $game->update(['score_confirmed_black' => true]);
        } else {
            $game->update(['score_confirmed_white' => true]);
        }
        $game->refresh();

        broadcast(new ScoreConfirmationUpdated($game));

        if ($game->score_confirmed_black && $game->score_confirmed_white) {
            $scoreResult = $this->board->calculateScore(
                $game->board_state,
                $game->board_size,
                $game->dead_stones ?? [],
                $game->captures_black,
                $game->captures_white,
                $game->komi
            );

            $winner = match ($scoreResult->winner) {
                'black' => $game->blackPlayer,
                'white' => $game->whitePlayer,
                default => null,
            };

            $this->finishGame($game, $scoreResult->result, $winner, 'score');
        }
    }

    public function cancelScoring(Game $game): void
    {
        $game->update([
            'status' => 'active',
            'consecutive_passes' => 0,
        ]);
        $game->refresh();
        broadcast(new ScoringCancelled($game));
    }

    public function abortGame(Game $game): Game
    {
        $game->update(['status' => 'aborted', 'end_reason' => 'abort', 'finished_at' => now()]);
        $game->room->update(['status' => 'cancelled']);
        broadcast(new GameEnded($game))->toOthers();

        return $game;
    }

    public function handleTimeout(Game $game, string $color): Game
    {
        $opponent = ($color === 'black') ? $game->whitePlayer : $game->blackPlayer;
        $result = ($color === 'black') ? 'W+T' : 'B+T';

        return $this->finishGame($game, $result, $opponent, 'timeout');
    }

    private function finishGame(Game $game, string $result, ?User $winner, string $endReason): Game
    {
        $game->update([
            'status' => 'finished',
            'result' => $result,
            'winner_id' => $winner?->id,
            'end_reason' => $endReason,
            'finished_at' => now(),
        ]);

        $game->room->update(['status' => 'finished']);
        $this->updatePlayerStats($game);
        $this->updatePlayerRatings($game);

        $game->refresh();
        broadcast(new GameEnded($game))->toOthers();

        return $game;
    }

    private function updatePlayerStats(Game $game): void
    {
        foreach (['black' => $game->black_player_id, 'white' => $game->white_player_id] as $color => $playerId) {
            if (! $playerId) {
                continue;
            }

            $stat = UserStat::firstOrCreate(['user_id' => $playerId], [
                'games_played' => 0, 'games_won' => 0, 'games_lost' => 0,
                'games_drawn' => 0, 'win_streak' => 0, 'best_win_streak' => 0, 'total_moves' => 0,
            ]);

            $stat->games_played++;

            if ($game->winner_id === $playerId) {
                $stat->games_won++;
                $stat->win_streak++;
                $stat->best_win_streak = max($stat->win_streak, $stat->best_win_streak);
            } elseif ($game->result === 'Draw') {
                $stat->games_drawn++;
                $stat->win_streak = 0;
            } else {
                $stat->games_lost++;
                $stat->win_streak = 0;
            }

            $stat->total_moves += $game->move_number;
            $stat->save();
        }
    }

    private function updatePlayerRatings(Game $game): void
    {
        // ไม่อัปเดต rating ถ้าไม่มีผู้ชนะ (draw หรือ abort)
        if (! $game->winner_id) {
            return;
        }

        // ไม่อัปเดต rating เมื่อเล่นกับบอท (เกมฝึกซ้อม)
        $black = User::find($game->black_player_id);
        $white = User::find($game->white_player_id);
        if (($black && $black->is_bot) || ($white && $white->is_bot)) {
            return;
        }

        $winnerId = $game->winner_id;
        $loserId  = ($game->black_player_id === $winnerId)
            ? $game->white_player_id
            : $game->black_player_id;

        if (! $winnerId || ! $loserId) {
            return;
        }

        $winner = User::find($winnerId);
        $loser  = User::find($loserId);

        if (! $winner || ! $loser) {
            return;
        }

        [$winnerChange, $loserChange] = RatingService::calculateChanges(
            $winner->rank_points,
            $loser->rank_points
        );

        $newWinnerPoints = max(0, $winner->rank_points + $winnerChange);
        $newLoserPoints  = max(0, $loser->rank_points  + $loserChange);

        $winner->update([
            'rank_points' => $newWinnerPoints,
            'rank'        => RatingService::getRankFromRating($newWinnerPoints),
        ]);

        $loser->update([
            'rank_points' => $newLoserPoints,
            'rank'        => RatingService::getRankFromRating($newLoserPoints),
        ]);
    }

    private function computeSecondsSpent(Game $game): int
    {
        // Use last_move_at if available (set on every move/pass).
        // Fall back to started_at for the very first move.
        $ref = $game->last_move_at ?? $game->started_at ?? Carbon::now();

        return (int) $ref->diffInSeconds(Carbon::now());
    }
}
