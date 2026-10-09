<?php

namespace App\Http\Middleware;

use App\Support\AdminConfirmation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class AdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        // Re-read privileges: an old session must never keep revoked administrator rights.
        $user = $request->user()?->fresh();
        abort_unless($user?->is_admin === true, 403);
        $allowedIps = config('security.admin_allowed_ips', []);
        abort_if($allowedIps && !IpUtils::checkIp($request->ip(), $allowedIps), 403);

        if (!AdminConfirmation::valid($request, $user)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Confirme ton mot de passe pour accéder à l’administration.', 'confirmation_url' => route('password.confirm')], 423);
            }
            $request->session()->put('url.intended', $request->isMethod('GET') ? $request->fullUrl() : route('admin.index'));
            return redirect()->route('password.confirm')->with('status', 'Pour protéger l’administration, confirme ton mot de passe.');
        }

        return $next($request);
    }
}
