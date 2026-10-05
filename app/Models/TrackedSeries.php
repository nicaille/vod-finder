<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackedSeries extends Model
{
    protected $table = 'tracked_series';
    protected $fillable = ['tmdb_id', 'name', 'synced_at'];
    protected $casts = ['synced_at' => 'datetime'];

    public function follows()
    {
        return $this->hasMany(SeriesFollow::class);
    }

    public function episodes()
    {
        return $this->hasMany(SeriesEpisode::class);
    }
}
