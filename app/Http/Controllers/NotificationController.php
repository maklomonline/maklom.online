<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function index(Request $request)
    {
        $query = AppNotification::where('user_id', $request->user()->id)->latest();

        if ($request->wantsJson() || $request->has('per_page')) {
            return response()->json($query->paginate((int) $request->query('per_page', 10)));
        }

        $notifications = $query->paginate(20);

        return view('notifications.index', compact('notifications'));
    }

    public function markRead(AppNotification $notification, Request $request)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->markAsRead();

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $this->notificationService->markAllRead($request->user());

        return back()->with('success', 'ทำเครื่องหมายอ่านแล้วทั้งหมด');
    }

    public function destroy(AppNotification $notification, Request $request)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403);
        }

        $notification->delete();

        return response()->json(['success' => true]);
    }
}
