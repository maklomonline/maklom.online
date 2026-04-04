<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice()
    {
        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('lobby')->with('success', 'ยืนยันอีเมลสำเร็จแล้ว ยินดีต้อนรับ!');
    }

    public function resend(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('lobby');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'ส่งลิงก์ยืนยันอีเมลใหม่แล้ว');
    }
}
