<?php

namespace App\Services;

use App\Models\PushSubscription;
use Composer\CaBundle\CaBundle;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Illuminate\Support\Facades\Log;

class BrowserPushService
{
    public function keys(): ?array
    {
        $path = config('webpush.key_file');
        if (!is_file($path)) return null;
        $keys = json_decode(file_get_contents($path), true);
        return isset($keys['publicKey'], $keys['privateKey']) ? $keys : null;
    }

    // Returns false only for an expired subscription; transient failures are retried.
    public function send(PushSubscription $subscription, array $payload): bool
    {
        $keys = $this->keys();
        if (!$keys) throw new \RuntimeException('Web push is not configured.');
        $webPush = $this->transport($keys);
        $context = ['subscription_id' => $subscription->id,
            'tag' => preg_match('/^(social|availability|episode|push-test)-[a-zA-Z0-9-]{1,64}$/', $payload['tag'] ?? '') ? $payload['tag'] : 'other',
            'urgency' => 'high', 'attempted_at' => now()->toIso8601String()];
        Log::info('Web push attempt', $context);
        try {
            $report = $webPush->sendOneNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => 'aes128gcm',
            ]), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            Log::warning('Web push transport failed', $context + ['exception_class' => get_class($e)]);
            throw $e;
        }
        Log::info('Web push result', $context + [
            'result' => $report->isSubscriptionExpired() ? 'expired' : ($report->isSuccess() ? 'accepted' : ($report->getResponse() ? 'rejected' : 'transport_failed')),
            'http_status' => $report->getResponse()?->getStatusCode(),
            'completed_at' => now()->toIso8601String(),
        ]);
        if ($report->isSubscriptionExpired()) return false;
        if (!$report->isSuccess()) throw new \RuntimeException('Push delivery failed.');
        return true;
    }
    protected function transport(array $keys): WebPush
    {
        // All payloads display a notification; high urgency requests delivery to sleeping devices.
        return new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey'],
        ]], ['TTL' => 86400, 'urgency' => 'high'], 10, ['verify' => ini_get('curl.cainfo') ?: CaBundle::getSystemCaRootBundlePath(), 'allow_redirects' => false]);
    }
}
