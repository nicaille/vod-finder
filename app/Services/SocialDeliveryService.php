<?php

namespace App\Services;

use App\Models\{SocialEvent, SocialDelivery, UserConnection};
use App\Mail\SocialAnnouncement;
use Illuminate\Support\Facades\{Mail, Log};
use Throwable;
class SocialDeliveryService
{
    public function __construct(private BrowserPushService $push)
    {
    }
    public function deliver(): array
    {
        $sent = 0;
        $failed = 0;
        foreach (SocialEvent::where('created_at', '>=', now()->subDay())->with(['user.pushSubscriptions', 'actor', 'recommendation', 'connection'])->lazyById(100) as $event) {
            $user = $event->user;
            if (!$user->notify_opt_in) {
                continue;
            }
            $pair = [$user->id, $event->actor_id];
            sort($pair);
            $relation = UserConnection::where('user_low_id', $pair[0])->where('user_high_id', $pair[1])->first();
            if (!$relation || $relation->status === 'blocked' || $relation->status === 'declined') {
                continue;
            }
            if ($event->kind === 'request' && ($relation->status !== 'pending' || $relation->requested_by !== $event->actor_id)) {
                continue;
            }
            if ($event->kind !== 'request' && $relation->status !== 'accepted') {
                continue;
            }
            $targets = [];
            if ($user->notify_email && $user->hasVerifiedEmail()) {
                $targets['email']['email'] = null;
            }
            if ($user->notify_web && $this->push->keys()) {
                foreach ($user->pushSubscriptions as $subscription) {
                    $targets['web'][$subscription->endpoint_hash] = $subscription;
                }
            }
            foreach ($targets as $channel => $destinations) {
                foreach ($destinations as $destination => $subscription) {
                    $delivery = SocialDelivery::firstOrCreate(['social_event_id' => $event->id, 'channel' => $channel, 'destination_key' => hash('sha256', $destination)]);
                    if ($delivery->sent_at || $delivery->attempts >= 5 || $delivery->retry_at?->isFuture()) {
                        continue;
                    }
                    $delivery->increment('attempts');
                    try {
                        if ($channel === 'email') {
                            if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) {
                                throw new \RuntimeException('Configure a delivery mailer.');
                            }
                            Mail::to($user->email)->send(new SocialAnnouncement($event));
                        } else if (!$this->push->send($subscription, ['title' => 'VOD Finder · ' . ($event->kind === 'recommendation' ? 'Recommandation' : 'Contacts'), 'body' => $event->label(), 'url' => parse_url($event->url(), PHP_URL_PATH), 'tag' => 'social-' . $event->id, 'image' => $event->recommendation?->content['image'] ?? null])) {
                            $subscription->delete();
                        }
                        $delivery->update(['sent_at' => now(), 'retry_at' => null]);
                        $sent++;
                    } catch (Throwable $e) {
                        $delivery->update(['retry_at' => now()->addMinutes(15 * $delivery->attempts)]);
                        Log::warning('Social notification delivery failed', ['event_id' => $event->id, 'channel' => $channel, 'exception_class' => get_class($e)]);
                        $failed++;
                    }
                }
            }
        }
        return compact('sent', 'failed');
    }
}
