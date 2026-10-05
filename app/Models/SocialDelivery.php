<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class SocialDelivery extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['sent_at' => 'datetime', 'retry_at' => 'datetime'];
}
