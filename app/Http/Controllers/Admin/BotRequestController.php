<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminLog;
use App\Models\BotRequest;
use App\Models\User;
use App\Models\UserStat;
use App\Services\NotificationService;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BotRequestController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    /** รายการคำขอสร้างบัญชีคอมพิวเตอร์ */
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');
        $requests = BotRequest::with('requester', 'reviewer')
            ->where('status', $status)
            ->latest()
            ->paginate(20);

        return view('admin.bot-requests.index', compact('requests', 'status'));
    }

    /** อนุมัติคำขอ → สร้างบัญชีคอมพิวเตอร์ */
    public function approve(BotRequest $botRequest, Request $request)
    {
        if (! $botRequest->isPending()) {
            return back()->with('error', 'คำขอนี้ถูกดำเนินการแล้ว');
        }

        $admin = $request->user();

        // สร้างบัญชีคอมพิวเตอร์
        $bot = User::create([
            'name'              => $botRequest->display_name,
            'username'          => $botRequest->username,
            'display_name'      => $botRequest->display_name,
            'email'             => $botRequest->username . '@bot.maklom.local',
            'password'          => $botRequest->password_hash,
            'rank'              => $botRequest->rank,
            'rank_points'       => RatingService::initialRatingForRank($botRequest->rank),
            'bio'               => $botRequest->bio,
            'is_bot'            => true,
            'bot_level'         => $botRequest->rank,
            'bot_api_token'     => Str::random(60),
            'email_verified_at' => now(),
            'locale'            => 'th',
        ]);

        UserStat::create([
            'user_id'        => $bot->id,
            'games_played'   => 0,
            'games_won'      => 0,
            'games_lost'     => 0,
            'games_drawn'    => 0,
            'win_streak'     => 0,
            'best_win_streak' => 0,
            'total_moves'    => 0,
        ]);

        $botRequest->update([
            'status'      => 'approved',
            'reviewed_by' => $admin->id,
        ]);

        // บันทึก admin log
        AdminLog::create([
            'admin_id'    => $admin->id,
            'action'      => 'approve_bot_request',
            'target_type' => 'bot_request',
            'target_id'   => $botRequest->id,
            'notes'       => "อนุมัติบัญชีคอมพิวเตอร์: {$bot->username}",
            'ip_address'  => $request->ip(),
        ]);

        // แจ้งเตือนผู้ขอ
        $this->notificationService->send(
            $botRequest->requester,
            'bot_approved',
            'คำขอบัญชีคอมพิวเตอร์ได้รับการอนุมัติ',
            "บัญชี {$bot->username} พร้อมใช้งานแล้ว ดาวน์โหลด bot client และเชื่อมต่อได้เลย",
            ['bot_username' => $bot->username, 'download_url' => route('bot.download')]
        );

        return back()->with('success', "อนุมัติแล้ว สร้างบัญชี {$bot->username} สำเร็จ");
    }

    /** ปฏิเสธคำขอ */
    public function reject(BotRequest $botRequest, Request $request)
    {
        if (! $botRequest->isPending()) {
            return back()->with('error', 'คำขอนี้ถูกดำเนินการแล้ว');
        }

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user();

        $botRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => $admin->id,
            'rejection_reason' => $validated['rejection_reason'] ?? null,
        ]);

        AdminLog::create([
            'admin_id'    => $admin->id,
            'action'      => 'reject_bot_request',
            'target_type' => 'bot_request',
            'target_id'   => $botRequest->id,
            'notes'       => "ปฏิเสธคำขอบัญชีคอมพิวเตอร์: {$botRequest->username}",
            'ip_address'  => $request->ip(),
        ]);

        $this->notificationService->send(
            $botRequest->requester,
            'bot_rejected',
            'คำขอบัญชีคอมพิวเตอร์ถูกปฏิเสธ',
            $validated['rejection_reason'] ?? 'ไม่ผ่านการอนุมัติ',
            []
        );

        return back()->with('success', 'ปฏิเสธคำขอแล้ว');
    }
}
