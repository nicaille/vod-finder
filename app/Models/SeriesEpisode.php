<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeriesEpisode extends Model
{
    protected $fillable = ['tracked_series_id', 'season_number', 'episode_number', 'name', 'air_date'];
    protected $casts = ['air_date' => 'date'];

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
