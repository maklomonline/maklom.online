<?php

namespace App\Http\Controllers;

use App\Models\GameRoom;
use App\Models\User;

class LobbyController extends Controller
{
    public function index()
    {
        $rooms = GameRoom::with('creator')
            ->whereIn('status', ['waiting', 'playing'])
            ->latest()
            ->paginate(20);

        $onlineUsers = User::online()->select('id', 'name', 'display_name', 'username', 'rank')->latest('last_seen_at')->get();
        $onlineCount = $onlineUsers->count();

        return view('lobby.index', compact('rooms', 'onlineCount', 'onlineUsers'));
    }
}
