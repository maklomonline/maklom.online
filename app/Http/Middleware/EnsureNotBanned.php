<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureNotBanned
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user && $user->isCurrentlyBanned()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $reason = $user->ban_reason ?? 'ไม่ระบุสาเหตุ';
            $until = $user->banned_until ? ' จนถึง '.$user->banned_until->format('d/m/Y H:i') : ' (ถาวร)';

            return redirect('/login')->withErrors([
                'email' => "บัญชีของคุณถูกระงับ{$until} เหตุผล: {$reason}",
            ]);
        }

        return $next($request);
    }
}
