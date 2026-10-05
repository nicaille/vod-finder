<?php

namespace App\Services;

use App\Models\PushSubscription;
use Composer\CaBundle\CaBundle;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

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
        $webPush = new WebPush(['VAPID' => [
            'subject' => config('webpush.subject'), 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey'],
        ]], ['TTL' => 86400], 10, ['verify' => ini_get('curl.cainfo') ?: CaBundle::getSystemCaRootBundlePath(), 'allow_redirects' => false]);
        $report = $webPush->sendOneNotification(Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->public_key,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => 'aes128gcm',
        ]), json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        if ($report->isSubscriptionExpired()) return false;
        if (!$report->isSuccess()) throw new \RuntimeException('Push delivery failed.');
        return true;
    }
}
