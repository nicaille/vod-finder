<?php

namespace App\Services;

use App\Models\{SocialEvent, SocialDelivery, UserConnection};
use App\Mail\SocialAnnouncement;
use Illuminate\Support\Facades\{Cache, Log};
use Throwable;
class SocialDeliveryService
{
    public function __construct(private BrowserPushService $push, private NotificationEmailSender $email)
    {
    }
    public function deliver(?int $eventId = null): array
    {
        $sent = 0;
        $failed = 0;
        $query = SocialEvent::where('created_at', '>=', now()->subDay());
        if ($eventId !== null) $query->whereKey($eventId);
        foreach ($query->with(['user.pushSubscriptions', 'actor', 'recommendation', 'connection'])->lazyById(100) as $event) {
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
            if ($user->notify_web && $this->push->keys()) {
                foreach ($user->pushSubscriptions as $subscription) {
                    $targets['web'][$subscription->endpoint_hash] = $subscription;
                }
            }
            if ($user->notify_email && $user->hasVerifiedEmail()) {
                $targets['email']['email'] = null;
            }
            foreach ($targets as $channel => $destinations) {
                foreach ($destinations as $destination => $subscription) {
                    // Immediate sends and the hourly cron share a lock for each destination.
                    $lock = Cache::lock('social-delivery-'.$event->id.'-'.$channel.'-'.hash('sha256', $destination), 120);
                    if (!$lock->get()) continue;
                    try {
                        $delivery = SocialDelivery::firstOrCreate(['social_event_id' => $event->id, 'channel' => $channel, 'destination_key' => hash('sha256', $destination)]);
                        if ($delivery->sent_at || $delivery->attempts >= 5 || $delivery->retry_at?->isFuture()) {
                            continue;
                        }
                        $delivery->increment('attempts');
                        $context = ['event_id' => $event->id, 'delivery_id' => $delivery->id,
                            'channel' => $channel, 'attempt' => $delivery->attempts,
                            'created_at' => $event->created_at->toIso8601String()];
                        Log::info('Social notification attempt', $context);
                        try {
                            $expired = false;
                            if ($channel === 'email') {
                                $this->email->send($user->email, new SocialAnnouncement($event));
                            } else if (!$this->push->send($subscription, ['title' => 'VOD Finder · ' . ($event->kind === 'recommendation' ? 'Recommandation' : 'Contacts'), 'body' => $event->label(), 'url' => parse_url($event->url(), PHP_URL_PATH), 'tag' => 'social-' . $event->id, 'image' => $event->recommendation?->content['image'] ?? null])) {
                                $expired = true;
                                $subscription->delete();
                            }
                            $delivery->update(['sent_at' => now(), 'retry_at' => null]);
                            Log::info('Social notification result', $context + [
                                'result' => $expired ? 'expired' : 'accepted',
                                'completed_at' => now()->toIso8601String(),
                            ]);
                            $sent++;
                        } catch (Throwable $e) {
                            $delivery->update(['retry_at' => now()->addMinutes(15 * $delivery->attempts)]);
                            Log::warning('Social notification delivery failed', $context + ['exception_class' => get_class($e), 'retry_at' => $delivery->retry_at->toIso8601String()]);
                            $failed++;
                        }
                    } finally {
                        $lock->release();
                    }
                }
            }
        }
        return compact('sent', 'failed');
    }
}
