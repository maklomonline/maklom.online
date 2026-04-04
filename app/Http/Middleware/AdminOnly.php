<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        if (! $request->user()?->is_admin) {
            abort(403, 'ไม่มีสิทธิ์เข้าถึงส่วนนี้');
        }

        return $next($request);
    }
}
