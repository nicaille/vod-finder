<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EpisodeAlert extends Model
{
    protected $fillable = ['user_id', 'series_episode_id', 'read_at'];
    protected $casts = ['read_at' => 'datetime'];

    public function scopeAnnounced($query)
    {
        return $query->whereHas('episode', fn ($episodes) => $episodes->announced());
    }

    public function episode()
    {
        return $this->belongsTo(SeriesEpisode::class, 'series_episode_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
