<?php

namespace App\Http\Middleware;

use App\Models\Game;
use Closure;
use Illuminate\Http\Request;

class GameParticipant
{
    public function handle(Request $request, Closure $next)
    {
        $game = $request->route('game');

        if (! $game instanceof Game) {
            $game = Game::findOrFail($request->route('game'));
        }

        $user = $request->user();
        if ($game->black_player_id !== $user->id && $game->white_player_id !== $user->id) {
            abort(403, 'คุณไม่ได้เป็นผู้เล่นในเกมนี้');
        }

        return $next($request);
    }
}
