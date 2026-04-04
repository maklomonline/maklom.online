<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\Game;
use App\Services\GameService;
use Illuminate\Http\Request;

class GameManagementController extends Controller
{
    public function __construct(private GameService $gameService) {}

    public function index(Request $request)
    {
        $query = Game::with('blackPlayer', 'whitePlayer')->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $games = $query->paginate(20)->withQueryString();

        return view('admin.games.index', compact('games'));
    }

    public function show(Game $game)
    {
        $game->load('blackPlayer', 'whitePlayer', 'moves');

        return view('admin.games.show', compact('game'));
    }

    public function abort(Game $game, Request $request)
    {
        $this->gameService->abortGame($game);

        AdminLog::create([
            'admin_id' => $request->user()->id,
            'action' => 'abort_game',
            'target_type' => 'game',
            'target_id' => $game->id,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'ยกเลิกเกมแล้ว');
    }
}
