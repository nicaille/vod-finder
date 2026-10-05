<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeriesFollow extends Model
{
    protected $fillable = ['user_id', 'tracked_series_id', 'alerts_enabled'];
    protected $casts = ['alerts_enabled' => 'boolean'];

    public function series()
    {
        return $this->belongsTo(TrackedSeries::class, 'tracked_series_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
