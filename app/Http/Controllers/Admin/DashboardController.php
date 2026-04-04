<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatMessage;
use App\Models\Game;
use App\Models\Group;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'users_online' => User::online()->count(),
            'active_games' => Game::where('status', 'active')->count(),
            'games_today' => Game::whereDate('created_at', today())->count(),
            'messages_today' => ChatMessage::whereDate('created_at', today())->count(),
            'new_users_today' => User::whereDate('created_at', today())->count(),
            'banned_users' => User::where('is_banned', true)->count(),
            'total_groups' => Group::count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
