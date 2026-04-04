<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Services\FriendService;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function __construct(
        private FriendService $friendService,
        private NotificationService $notificationService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $friends = $this->friendService->getFriends($user);
        $pending = $this->friendService->getPendingRequests($user);
        $blocked = Friendship::with('addressee')->where('requester_id', $user->id)->where('status', 'blocked')->get();

        return view('friends.index', compact('friends', 'pending', 'blocked'));
    }

    public function sendRequest(User $user, Request $request)
    {
        $from = $request->user();

        if ($from->id === $user->id) {
            return back()->withErrors(['friend' => 'ไม่สามารถเพิ่มตัวเองเป็นเพื่อนได้']);
        }

        $friendship = $this->friendService->sendRequest($from, $user);

        $this->notificationService->send(
            $user,
            'friend_request',
            "{$from->getDisplayName()} ส่งคำขอเป็นเพื่อนถึงคุณ",
            null,
            ['friendship_id' => $friendship->id, 'requester_id' => $from->id]
        );

        return back()->with('success', 'ส่งคำขอเป็นเพื่อนแล้ว');
    }

    public function acceptRequest(Friendship $friendship, Request $request)
    {
        $this->friendService->acceptRequest($request->user(), $friendship->id);

        return back()->with('success', 'ยอมรับคำขอเป็นเพื่อนแล้ว');
    }

    public function declineRequest(Friendship $friendship, Request $request)
    {
        $this->friendService->declineRequest($request->user(), $friendship->id);

        return back()->with('success', 'ปฏิเสธคำขอแล้ว');
    }

    public function block(User $user, Request $request)
    {
        $this->friendService->block($request->user(), $user);

        return back()->with('success', "บล็อก {$user->getDisplayName()} แล้ว");
    }

    public function unblock(User $user, Request $request)
    {
        $this->friendService->unblock($request->user(), $user);

        return back()->with('success', "ยกเลิกบล็อก {$user->getDisplayName()} แล้ว");
    }

    public function removeFriend(User $user, Request $request)
    {
        $this->friendService->removeFriend($request->user(), $user);

        return back()->with('success', 'ลบเพื่อนแล้ว');
    }
}
