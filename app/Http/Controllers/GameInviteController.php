<?php

namespace App\Http\Controllers;

use App\Models\GameInvite;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class GameInviteController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function send(User $user, Request $request)
    {
        $request->validate(['room_id' => ['required', 'exists:game_rooms,id']]);

        $room = GameRoom::findOrFail($request->room_id);
        $from = $request->user();

        $invite = GameInvite::create([
            'room_id' => $room->id,
            'inviter_id' => $from->id,
            'invitee_id' => $user->id,
            'status' => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $this->notificationService->send(
            $user,
            'game_invite',
            "{$from->getDisplayName()} ชวนคุณเล่นหมากล้อม",
            "ห้อง: {$room->name}",
            ['invite_id' => $invite->id, 'room_id' => $room->id]
        );

        return response()->json(['success' => true, 'message' => 'ส่งคำเชิญแล้ว']);
    }

    public function accept(GameInvite $invite, Request $request)
    {
        if ($invite->invitee_id !== $request->user()->id) {
            abort(403);
        }

        if ($invite->isExpired()) {
            return back()->withErrors(['invite' => 'คำเชิญหมดอายุแล้ว']);
        }

        $invite->update(['status' => 'accepted']);

        return redirect()->route('rooms.show', $invite->room);
    }

    public function decline(GameInvite $invite, Request $request)
    {
        if ($invite->invitee_id !== $request->user()->id) {
            abort(403);
        }

        $invite->update(['status' => 'declined']);

        return response()->json(['success' => true]);
    }
}
