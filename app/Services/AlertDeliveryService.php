<?php

namespace App\Services;

use App\Mail\EpisodeAnnouncement;
use App\Models\AlertDelivery;
use App\Models\EpisodeAlert;
use App\Models\SeriesFollow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AlertDeliveryService
{
    public function __construct(private BrowserPushService $push)
    {
    }

    public function deliver(): array
    {
        $sent = 0;
        $failed = 0;
        // Do not send a historical backlog when a user first enables a new channel.
        foreach (EpisodeAlert::announced()->where('created_at', '>=', now()->subDay())->with(['episode.series', 'user.pushSubscriptions'])->lazyById(100) as $alert) {
            $user = $alert->user;
            if (!$user->notify_opt_in || !SeriesFollow::where('user_id', $user->id)->where('tracked_series_id', $alert->episode->tracked_series_id)->where('alerts_enabled', true)->exists()) continue;
            $targets = [];
            if ($user->notify_email && $user->hasVerifiedEmail()) $targets['email']['email'] = null;
            if ($user->notify_web && $this->push->keys()) {
                foreach ($user->pushSubscriptions as $subscription) $targets['web'][$subscription->endpoint_hash] = $subscription;
            }
            foreach ($targets as $channel => $destinations) {
                foreach ($destinations as $destination => $subscription) {
                    $delivery = AlertDelivery::firstOrCreate(['episode_alert_id' => $alert->id, 'channel' => $channel, 'destination_key' => hash('sha256', $destination)]);
                    if ($delivery->sent_at || $delivery->attempts >= 5 || $delivery->retry_at?->isFuture()) continue;
                    $delivery->increment('attempts');
                    try {
                        if ($channel === 'email') {
                            // A log/array mailer must never be counted as an actual e-mail delivery.
                            if (in_array(config('mail.default'), ['log', 'array', 'failover'], true)) throw new \RuntimeException('Configure a delivery mailer.');
                            Mail::to($user->email)->send(new EpisodeAnnouncement($alert));
                        } else {
                            $ok = $this->push->send($subscription, [
                                'title' => $alert->episode->series->name.' · '.$alert->episode->code,
                                'body' => 'Diffusion annoncée le '.$alert->episode->air_date->format('d/m/Y'),
                                'url' => '/series', 'tag' => 'episode-'.$alert->id,
                            ]);
                            if (!$ok) $subscription->delete();
                        }
                        $delivery->update(['sent_at' => now(), 'retry_at' => null]);
                        $sent++;
                    } catch (Throwable $e) {
                        $delivery->update(['retry_at' => now()->addMinutes(15 * $delivery->attempts)]);
                        Log::warning('Episode notification delivery failed', ['alert_id' => $alert->id, 'channel' => $channel, 'exception_class' => get_class($e)]);
                        $failed++;
                    }
                }
            }
        }
        return compact('sent', 'failed');
    }
}
