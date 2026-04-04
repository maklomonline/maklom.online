<?php

namespace App\Http\Controllers;

use App\Models\BotRequest;
use App\Services\RatingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class BotServerController extends Controller
{
    /** หน้าฟอร์มสมัครบัญชีคอมพิวเตอร์ */
    public function showRegisterForm()
    {
        $botRanks = RatingService::allRanks();

        return view('bot.register', compact('botRanks'));
    }

    /** รับคำขอสร้างบัญชีคอมพิวเตอร์ */
    public function submitRegister(Request $request)
    {
        $allRanks = RatingService::allRanks();

        $validated = $request->validate([
            'username'     => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/',
                                Rule::unique('users', 'username'),
                                Rule::unique('bot_requests', 'username')],
            'display_name' => ['required', 'string', 'min:2', 'max:50'],
            'rank'         => ['required', 'string', Rule::in($allRanks)],
            'bio'          => ['nullable', 'string', 'max:300'],
            'password'     => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'username.regex' => 'ชื่อผู้ใช้ต้องมีเฉพาะตัวอักษรภาษาอังกฤษ ตัวเลข หรือ _',
            'username.unique' => 'ชื่อผู้ใช้นี้ถูกใช้แล้ว',
            'password.confirmed' => 'รหัสผ่านไม่ตรงกัน',
        ]);

        BotRequest::create([
            'requester_id' => $request->user()->id,
            'username'     => $validated['username'],
            'display_name' => $validated['display_name'],
            'rank'         => $validated['rank'],
            'bio'          => $validated['bio'] ?? null,
            'password_hash' => Hash::make($validated['password']),
            'status'       => 'pending',
        ]);

        return redirect()->route('bot.register')
            ->with('success', 'ส่งคำขอสร้างบัญชีคอมพิวเตอร์แล้ว รอแอดมินอนุมัติ');
    }

    /** หน้าดาวน์โหลด bot client */
    public function download()
    {
        return view('bot.download');
    }
}
