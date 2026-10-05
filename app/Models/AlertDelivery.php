<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertDelivery extends Model
{
    protected $fillable = ['episode_alert_id', 'channel', 'destination_key', 'attempts', 'sent_at', 'retry_at'];
    protected $casts = ['sent_at' => 'datetime', 'retry_at' => 'datetime'];
}
