<?php

namespace App\Http\Controllers;

use App\Events\LobbyRoomUpdated;
use App\Http\Requests\CreateRoomRequest;
use App\Models\ChatRoom;
use App\Models\GameRoom;
use App\Services\GameService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GameRoomController extends Controller
{
    public function index()
    {
        $rooms = GameRoom::with('creator')->whereIn('status', ['waiting', 'playing'])->latest()->paginate(20);

        return view('rooms.index', compact('rooms'));
    }

    public function create()
    {
        return view('rooms.create');
    }

    public function store(CreateRoomRequest $request)
    {
        $data = $request->validated();
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $data['creator_id'] = $request->user()->id;

        $room = GameRoom::create($data);
        ChatRoom::forGame($room->id); // ensure chat room exists

        broadcast(new LobbyRoomUpdated($room, 'created'))->toOthers();

        return redirect()->route('rooms.show', $room);
    }

    public function show(GameRoom $room)
    {
        $room->load('creator', 'currentGame.blackPlayer', 'currentGame.whitePlayer');

        return view('rooms.show', compact('room'));
    }

    public function join(GameRoom $room, Request $request, GameService $gameService)
    {
        if ($room->status !== 'waiting') {
            return back()->withErrors(['room' => 'ห้องนี้ไม่พร้อมรับผู้เล่นแล้ว']);
        }

        if ($room->is_private && $room->password) {
            $request->validate(['password' => ['required', 'string']]);
            if (! $room->checkPassword($request->password)) {
                return back()->withErrors(['password' => 'รหัสผ่านไม่ถูกต้อง']);
            }
        }

        $creator = $room->creator;
        $joiner = $request->user();

        if ($creator->id === $joiner->id) {
            return back()->withErrors(['room' => 'คุณเป็นผู้สร้างห้องนี้อยู่แล้ว']);
        }

        // Randomly assign colors
        if (rand(0, 1)) {
            $black = $creator;
            $white = $joiner;
        } else {
            $black = $joiner;
            $white = $creator;
        }

        $game = $gameService->createGame($room, $black, $white);

        // Notify the creator (still waiting on rooms.show) to redirect
        $room->refresh()->load('currentGame');
        broadcast(new LobbyRoomUpdated($room, 'updated'))->toOthers();

        return redirect()->route('games.show', $game);
    }

    public function status(GameRoom $room)
    {
        return response()->json([
            'status' => $room->status,
            'gameId' => $room->status === 'playing' ? $room->currentGame?->id : null,
        ]);
    }

    public function leave(GameRoom $room, Request $request)
    {
        if ($room->creator_id === $request->user()->id && $room->status === 'waiting') {
            $room->update(['status' => 'cancelled']);
            broadcast(new LobbyRoomUpdated($room, 'deleted'))->toOthers();

            return redirect()->route('lobby')->with('success', 'ยกเลิกห้องแล้ว');
        }

        return redirect()->route('lobby');
    }

    public function destroy(GameRoom $room, Request $request)
    {
        if ($room->creator_id !== $request->user()->id && ! $request->user()->is_admin) {
            abort(403);
        }

        $room->update(['status' => 'cancelled']);
        broadcast(new LobbyRoomUpdated($room, 'deleted'))->toOthers();

        return redirect()->route('lobby')->with('success', 'ลบห้องแล้ว');
    }
}
