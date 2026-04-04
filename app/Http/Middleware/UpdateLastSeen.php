<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()) {
            $userId = $request->user()->id;
            $cacheKey = "user.last_seen.{$userId}";

            if (! Cache::has($cacheKey)) {
                $request->user()->update(['last_seen_at' => now()]);
                Cache::put($cacheKey, true, 60); // throttle to once per minute
            }
        }

        return $next($request);
    }
}
