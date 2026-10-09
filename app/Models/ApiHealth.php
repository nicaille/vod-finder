<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiHealth extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['last_success_at' => 'datetime', 'last_failure_at' => 'datetime', 'quota_headers' => 'array', 'quota_observed_at' => 'datetime'];
    protected $table = 'api_health';
}
