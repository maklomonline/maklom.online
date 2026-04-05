<?php

namespace App\Http\Controllers;

use App\Http\Requests\MakeMoveRequest;
use App\Jobs\BotMoveJob;
use App\Models\Game;
use App\Models\GameAnnotation;
use App\Models\GameObserver;
use App\Models\User;
use App\Services\GameService;
use App\Services\GoEngine\GoRuleException;
use App\Services\SgfService;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(
        private GameService $gameService,
        private SgfService $sgfService,
    ) {}

    public function show(Game $game, Request $request)
    {
        $game->load('blackPlayer', 'whitePlayer', 'moves', 'room');
        $user = $request->user();
        $myColor = $game->getPlayerColor($user);

        // Add as observer if not a player
        if ($myColor === null && $user) {
            GameObserver::firstOrCreate(['game_id' => $game->id, 'user_id' => $user->id]);
        }

        $chatRoom = $game->chatRoom();

        // For finished games: pre-compute board states and load annotations
        $boardStates = null;
        $annotations = collect();
        if ($game->status === 'finished') {
            $boardStates = $this->sgfService->computeBoardStates($game);
            $annotations = GameAnnotation::where('game_id', $game->id)
                ->with('user')
                ->orderBy('created_at')
                ->get()
                ->map(fn ($a) => [
                    'id'         => $a->id,
                    'title'      => $a->title,
                    'user'       => $a->user?->getDisplayName() ?? '?',
                    'user_id'    => $a->user_id,
                    'sgf'        => $a->sgf_content,
                    'created_at' => $a->created_at->toDateTimeString(),
                ]);
        }

        return view('games.show', compact('game', 'myColor', 'chatRoom', 'boardStates', 'annotations'));
    }

    public function move(Game $game, MakeMoveRequest $request)
    {
        try {
            $game = $this->gameService->makeMove($game, $request->user(), $request->coordinate);
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\App\Services\ClockTimeoutException $e) {
            $finished = $this->gameService->handleTimeout($game, $game->current_color);

            return response()->json(['error' => $e->getMessage(), 'timeout' => true, 'result' => $finished->result], 422);
        }

        // ถ้าคู่ต่อสู้เป็นบอท ให้บอทเล่นตาถัดไป
        $this->dispatchBotMoveIfNeeded($game);

        return response()->json([
            'success' => true,
            'boardState' => $game->board_state,
            'currentColor' => $game->current_color,
            'capturesBlack' => $game->captures_black,
            'capturesWhite' => $game->captures_white,
            'koPoint' => $game->ko_point,
            'blackTimeLeft' => $game->black_time_left,
            'whiteTimeLeft' => $game->white_time_left,
            'blackPeriodsLeft' => $game->black_periods_left,
            'whitePeriodsLeft' => $game->white_periods_left,
            'moveNumber' => $game->move_number,
        ]);
    }

    public function pass(Game $game, Request $request)
    {
        try {
            $game = $this->gameService->passMove($game, $request->user());
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\App\Services\ClockTimeoutException $e) {
            $finished = $this->gameService->handleTimeout($game, $game->current_color);

            return response()->json(['error' => $e->getMessage(), 'timeout' => true, 'result' => $finished->result], 422);
        }

        // ถ้าคู่ต่อสู้เป็นบอทและเกมยังเล่นอยู่ ให้บอทเล่นตาถัดไป
        if ($game->isActive()) {
            $this->dispatchBotMoveIfNeeded($game);
        }

        return response()->json([
            'success' => true,
            'currentColor' => $game->current_color,
            'consecutivePasses' => $game->consecutive_passes,
            'status' => $game->status,
            'boardState' => $game->board_state,
            'komi' => $game->komi,
            'capturesBlack' => $game->captures_black,
            'capturesWhite' => $game->captures_white,
            'blackTimeLeft' => $game->black_time_left,
            'whiteTimeLeft' => $game->white_time_left,
            'blackPeriodsLeft' => $game->black_periods_left,
            'whitePeriodsLeft' => $game->white_periods_left,
        ]);
    }

    public function claimTimeout(Game $game, Request $request)
    {
        if (! $game->isActive()) {
            return response()->json(['error' => 'Game is not active'], 422);
        }

        $timedOutColor = $request->input('color');
        if (! in_array($timedOutColor, ['black', 'white'])) {
            return response()->json(['error' => 'Invalid color'], 422);
        }

        $finished = $this->gameService->handleTimeout($game, $timedOutColor);

        return response()->json([
            'success' => true,
            'result' => $finished->result,
        ]);
    }

    public function confirmScore(Game $game, Request $request)
    {
        try {
            $outcome = $this->gameService->confirmScore($game, $request->user());
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        // If opponent is a bot and game still in scoring, bot auto-confirms
        if (! $outcome['finished'] && $game->status === 'scoring') {
            $user = $request->user();
            $opponent = $game->getOpponent($user);
            if ($opponent && $opponent->is_bot) {
                $game->refresh();
                $outcome = $this->gameService->confirmScore($game, $opponent);
            }
        }

        $game->refresh();

        return response()->json([
            'success' => true,
            'finished' => $outcome['finished'],
            'result' => $outcome['result'],
            'scoreConfirmedBlack' => (bool) $game->score_confirmed_black,
            'scoreConfirmedWhite' => (bool) $game->score_confirmed_white,
        ]);
    }

    public function toggleDeadGroup(Game $game, Request $request)
    {
        $request->validate(['coordinate' => ['required', 'string']]);

        if ($game->status !== 'scoring') {
            return response()->json(['error' => 'ไม่ได้อยู่ในขั้นตอนนับคะแนน'], 422);
        }

        $game = $this->gameService->toggleDeadGroup($game, $request->coordinate);

        return response()->json([
            'success' => true,
            'deadStones' => $game->dead_stones ?? [],
            'scoreConfirmedBlack' => $game->score_confirmed_black,
            'scoreConfirmedWhite' => $game->score_confirmed_white,
        ]);
    }

    public function cancelScoring(Game $game, Request $request)
    {
        if ($game->status !== 'scoring') {
            return response()->json(['error' => 'ไม่ได้อยู่ในขั้นตอนนับคะแนน'], 422);
        }

        $this->gameService->cancelScoring($game);

        return response()->json([
            'success' => true,
            'currentColor' => $game->current_color,
        ]);
    }

    public function resign(Game $game, Request $request)
    {
        try {
            $game = $this->gameService->resignGame($game, $request->user());
        } catch (GoRuleException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'result' => $game->result,
            'status' => $game->status,
        ]);
    }

    /** Dispatch BotMoveJob ถ้า current player เป็นบอท */
    private function dispatchBotMoveIfNeeded(Game $game): void
    {
        $currentColor = $game->current_color;
        $currentPlayer = ($currentColor === 'black') ? $game->blackPlayer : $game->whitePlayer;

        if ($currentPlayer && $currentPlayer->is_bot) {
            BotMoveJob::dispatch($game->id, $currentColor)->delay(now()->addSecond());
        }
    }

    public function history(User $user)
    {
        $games = Game::where('black_player_id', $user->id)
            ->orWhere('white_player_id', $user->id)
            ->with('blackPlayer', 'whitePlayer')
            ->where('status', 'finished')
            ->latest()
            ->paginate(20);

        return view('games.history', compact('games', 'user'));
    }
}
