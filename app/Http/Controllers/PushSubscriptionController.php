<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\BrowserPushService;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function key(BrowserPushService $push)
    {
        $keys = $push->keys();
        return response()->json(['publicKey' => $keys['publicKey'] ?? null]);
    }

    public function store(Request $request, BrowserPushService $push)
    {
        abort_unless($push->keys(), 503, 'Les notifications navigateur ne sont pas encore configurées.');
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048', function ($attribute, $value, $fail) {
                $url = parse_url($value);
                $host = strtolower($url['host'] ?? '');
                $allowed = $host === 'fcm.googleapis.com' || $host === 'updates.push.services.mozilla.com'
                    || $host === 'web.push.apple.com' || str_ends_with($host, '.notify.windows.com');
                if (!$allowed || ($url['scheme'] ?? '') !== 'https' || isset($url['user']) || isset($url['pass'])
                    || isset($url['fragment']) || (isset($url['port']) && $url['port'] !== 443)) {
                    $fail('Service de notifications navigateur non reconnu.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{87}=?$/'],
            'keys.auth' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{22}={0,2}$/'],
        ]);
        $hash = hash('sha256', $data['endpoint']);
        $existing = PushSubscription::where('endpoint_hash', $hash)->first();
        abort_if($existing && $existing->user_id !== $request->user()->id, 409, 'Ce navigateur est associé à un autre compte. Désactive ses notifications avant de changer de compte.');
        $subscription = PushSubscription::updateOrCreate(['endpoint_hash' => $hash], [
            'user_id' => $request->user()->id, 'endpoint' => $data['endpoint'],
            'public_key' => $data['keys']['p256dh'], 'auth_token' => $data['keys']['auth'],
        ]);
        return response()->json(['success' => true, 'id' => $subscription->id]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        return response()->json(['success' => true]);
    }

    public function status(Request $request)
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        return response()->json(['registered' => $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $data['endpoint']))->exists()]);
    }
}
