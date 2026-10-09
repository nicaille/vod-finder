<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchedTitle extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['watched_at' => 'datetime'];
    
}
