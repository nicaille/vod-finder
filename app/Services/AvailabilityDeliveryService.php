<?php

namespace App\Services;

use App\Models\{AvailabilityAlert, AvailabilityDelivery};
use App\Mail\AvailabilityAnnouncement;
use Illuminate\Support\Facades\Log;
use Throwable;

class AvailabilityDeliveryService
{
    public function __construct(private BrowserPushService $push, private NotificationEmailSender $email) {}

    public function deliver(): array
    {
        $sent = 0; $failed = 0;
        foreach (AvailabilityAlert::where('created_at','>=',now()->subDays(2))->with(['user.pushSubscriptions','user.platformSubscriptions','item'])->lazyById(100) as $alert) {
            $user = $alert->user;
            if (!$alert->item || !$user->notify_opt_in || !$user->notify_platform_updates || $user->watchedTitles()->where('type',$alert->type)->where('tmdb_id',$alert->tmdb_id)->exists()) continue;
            $currentKeys = array_map(AvailabilityWatchService::key(...), $alert->item->availability_providers ?? []);
            $eligible = array_values(array_filter($alert->providers, fn ($p) => in_array(AvailabilityWatchService::key($p), $currentKeys, true) && $user->platformSubscriptions->contains(fn ($sub) => $sub->pivot->is_active && $sub->pivot->notify_opt_in && AvailabilityWatchService::matches($p,$sub))));
            if (!$eligible) continue;
            $alert->providers = $eligible;
            $targets = [];
            if ($user->notify_email && $user->hasVerifiedEmail()) $targets['email']['email'] = null;
            if ($user->notify_web && $this->push->keys()) foreach ($user->pushSubscriptions as $sub) $targets['web'][$sub->endpoint_hash] = $sub;
            foreach ($targets as $channel=>$destinations) foreach ($destinations as $destination=>$sub) {
                $delivery = AvailabilityDelivery::firstOrCreate(['availability_alert_id'=>$alert->id,'channel'=>$channel,'destination_key'=>hash('sha256',$destination)]);
                if ($delivery->sent_at || $delivery->attempts >= 5 || $delivery->retry_at?->isFuture()) continue;
                $delivery->increment('attempts');
                try {
                    if ($channel === 'email') $this->email->send($user->email,new AvailabilityAnnouncement($alert));
                    elseif (!$this->push->send($sub,['title'=>'Disponible maintenant · '.$alert->title,'body'=>'Inclus sur '.$alert->providerLabel(),'url'=>parse_url($alert->url(),PHP_URL_PATH),'tag'=>'availability-'.$alert->id,'image'=>$alert->poster])) $sub->delete();
                    $delivery->update(['sent_at'=>now(),'retry_at'=>null]); $sent++;
                } catch (Throwable $e) {
                    $delivery->update(['retry_at'=>now()->addMinutes(15 * $delivery->attempts)]);
                    Log::warning('Availability notification delivery failed',['alert_id'=>$alert->id,'channel'=>$channel,'exception_class'=>get_class($e)]); $failed++;
                }
            }
        }
        return compact('sent','failed');
    }
}
