<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeriesEpisode extends Model
{
    protected $fillable = ['tracked_series_id', 'season_number', 'episode_number', 'name', 'air_date', 'airs_at', 'calendar_source', 'is_next_announced'];
    protected $casts = ['air_date' => 'date', 'is_next_announced' => 'boolean'];

    // Database timestamps are always UTC, independently of Laravel's application timezone.
    public function getAirsAtAttribute($value): ?\Carbon\CarbonImmutable
    {
        return $value ? \Carbon\CarbonImmutable::parse($value, 'UTC') : null;
    }

    public function setAirsAtAttribute($value): void
    {
        $this->attributes['airs_at'] = $value === null ? null : \Carbon\CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d H:i:s');
    }

    public function scopeUpcoming($query)
    {
        $today = now('Europe/Paris')->toDateString();
        return $query->where(function ($episodes) use ($today) {
            $episodes->where('airs_at', '>', now()->utc()->format('Y-m-d H:i:s'))
                ->orWhere(function ($dates) use ($today) {
                    $dates->whereNull('airs_at')->where(function ($dates) use ($today) {
                        $dates->where('air_date', '>=', $today)
                            // A next episode dated yesterday may still be upcoming locally.
                            // Keep the original date and explicitly flag the uncertain time.
                            ->orWhere(fn ($next) => $next->where('is_next_announced', true)->where('air_date', now('Europe/Paris')->subDay()->toDateString()))
                            ->orWhere(fn ($next) => $next->where('is_next_announced', true)->whereNull('air_date'));
                    });
                });
        });
    }

    public function scopeAnnounced($query)
    {
        return $query->where(fn ($episodes) => $episodes
            ->where('airs_at', '<=', now()->utc()->format('Y-m-d H:i:s'))
            ->orWhere(fn ($dates) => $dates->whereNull('airs_at')->where('air_date', '<=', now('Europe/Paris')->toDateString())
                ->where(fn ($next) => $next->where('is_next_announced', false)
                    ->orWhere('air_date', '<', now('Europe/Paris')->subDay()->toDateString()))));
    }

    public function getBroadcastLabelAttribute(): string
    {
        if ($this->airs_at) {
            return 'Diffusion annoncée le '.$this->airs_at->timezone('Europe/Paris')->format('d/m/Y à H:i').' (heure de Paris)';
        }
        return $this->air_date ? 'Diffusion annoncée le '.$this->air_date->format('d/m/Y') : 'Date de diffusion à confirmer';
    }

    public function setAirDateAttribute($value): void
    {
        $this->attributes['air_date'] = $value === null ? null : \Carbon\Carbon::parse($value)->toDateString();
    }

    public function series()
    {
        return $this->belongsTo(TrackedSeries::class, 'tracked_series_id');
    }

    public function getCodeAttribute(): string
    {
        return sprintf('S%02dE%02d', $this->season_number, $this->episode_number);
    }
}
