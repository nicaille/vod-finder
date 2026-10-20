<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AuthenticateCloudflareCron
{
    public function handle(Request $request, Closure $next)
    {
        $token = config('cloudflare-cron.token');
        abort_unless(config('cloudflare-cron.enabled') && is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token), 404);
        abort_unless($request->isSecure() || app()->environment('local', 'testing'), 403);
        $provided = $request->bearerToken();
        abort_unless(is_string($provided) && hash_equals($token, $provided), 401);
        return $next($request);
    }
}
