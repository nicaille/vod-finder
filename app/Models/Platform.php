<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Platform extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\User::class, 'user_platform_subscriptions')
            ->withPivot(['subscribed_via_platform_id', 'is_active', 'notify_opt_in'])
            ->withTimestamps();
    }
}
