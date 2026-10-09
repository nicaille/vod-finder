<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $policy = "base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'";

        if ($request->is('admin', 'admin/*', 'confirm-password')) {
            $development = app()->environment('local', 'development');
            $scripts = $development ? "'self' 'unsafe-eval' http://localhost:5173 http://127.0.0.1:5173" : "'self'";
            $connections = $development ? "'self' http://localhost:5173 http://127.0.0.1:5173 ws://localhost:5173 ws://127.0.0.1:5173" : "'self'";
            // Alpine's standard build uses eval; the password confirmation page does not need it.
            $policy .= "; default-src 'self'; script-src $scripts; connect-src $connections; style-src 'self' 'unsafe-inline' https://fonts.bunny.net; font-src 'self' https://fonts.bunny.net; img-src 'self' https://image.tmdb.org data:; frame-src 'none'";
        }
        $response->headers->set('Content-Security-Policy', $policy);
        if ($request->isSecure() && app()->environment('production')) {
            // Do not include subdomains: other applications on the domain may use a different policy.
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($request->user() || $request->is('admin', 'admin/*', 'login', 'register', '*password*', 'verify-email*')) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        if ($request->is('admin', 'admin/*') && !$request->isMethod('HEAD')) {
            Log::channel('security')->info('admin.request', [
                'actor_id' => $request->user()?->id,
                'route' => $request->route()?->getName(),
                'method' => $request->method(),
                'status' => $response->getStatusCode(),
                'target_user_id' => $request->route('user') instanceof \App\Models\User ? $request->route('user')->id : null,
            ]);
        }
        return $response;
    }
}
