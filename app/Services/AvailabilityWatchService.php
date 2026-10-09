<?php

namespace App\Services;

use App\Models\{WatchlistItem, AvailabilityAlert};
use Illuminate\Support\Facades\DB;

class AvailabilityWatchService
{
    public function __construct(private TmdbService $tmdb) {}

    public static function key(array $provider): string { return $provider['slug'].'|'.($provider['via'] ?? ''); }

    public static function matches(array $provider, $subscription): bool
    {
        $via = $subscription->pivot->subscribed_via_platform_id;
        $expected = $via ? \App\Models\Platform::find($via)?->slug : null;
        return $provider['access'] === 'flatrate' && $provider['slug'] === $subscription->slug && ($provider['via'] ?? null) === $expected;
    }

    public function sync(): array
    {
        $checked = 0; $alerts = 0; $failed = 0; $snapshots = [];
        foreach (WatchlistItem::with(['user.platformSubscriptions'])->lazyById(100) as $item) {
            if ($item->user->watchedTitles()->where('type',$item->type)->where('tmdb_id',$item->tmdb_id)->exists()) continue;
            $subscriptions = $item->user->platformSubscriptions->filter(fn ($sub) => $sub->pivot->is_active);
            if ($subscriptions->isEmpty()) continue;
            // The cache shares lookups across users while retaining coverage requirements.
            $slugs = $subscriptions->pluck('slug')->sort()->values()->all();
            $key = $item->type.':'.$item->tmdb_id.':'.implode(',', $slugs);
            if (!array_key_exists($key, $snapshots)) $snapshots[$key] = $this->tmdb->getAvailabilitySnapshot($item->tmdb_id,$item->type,'FR',$slugs);
            $providers = $snapshots[$key];
            if ($providers === null) { $failed++; continue; }
            $included = collect($providers)->filter(fn ($p) => ($p['access'] ?? null) === 'flatrate')->unique(fn ($p) => self::key($p))->values()->all();
            $alerts += DB::transaction(function () use ($item, $included, $subscriptions) {
                $current = WatchlistItem::whereKey($item->id)->lockForUpdate()->first();
                if (!$current || $current->user->watchedTitles()->where('type',$current->type)->where('tmdb_id',$current->tmdb_id)->exists()) return 0;
                $previous = array_map(self::key(...), $current->availability_providers ?? []);
                $new = array_values(array_filter($included, fn ($p) => !in_array(self::key($p), $previous, true)));
                $eligible = array_values(array_filter($new, fn ($p) => $subscriptions->contains(fn ($sub) => $sub->pivot->notify_opt_in && self::matches($p, $sub))));
                $baseline = $current->availability_checked_at !== null;
                $current->availability_revision++;
                $current->availability_providers = $included; $current->availability_checked_at = now();
                $current->save();
                if (!$baseline || !$eligible || !$current->user->notify_opt_in || !$current->user->notify_platform_updates) return 0;
                AvailabilityAlert::create(['user_id'=>$current->user_id,'watchlist_item_id'=>$current->id,'revision'=>$current->availability_revision,'tmdb_id'=>$current->tmdb_id,'type'=>$current->type,'title'=>$current->title,'poster'=>$current->poster,'providers'=>$eligible]);
                return 1;
            });
            $checked++;
        }
        return compact('checked','alerts','failed');
    }
}
