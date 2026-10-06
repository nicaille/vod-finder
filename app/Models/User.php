<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'nickname',
        'notify_opt_in',
        'notify_platform_updates',
        'notify_email',
        'notify_web',
        'directory_visible', 'share_real_name',
    ];

    protected $casts = [
        'is_admin' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'notify_opt_in' => 'boolean',
        'notify_platform_updates' => 'boolean',
        'notify_email' => 'boolean',
        'notify_web' => 'boolean',
        'directory_visible' => 'boolean', 'share_real_name' => 'boolean',
    ];

    protected $hidden = [
        'password',
        'remember_token', 'contact_token',
    ];

    public function socialName(): string
    {
        if ($this->share_real_name && trim($this->first_name.' '.$this->last_name) !== '') return trim($this->first_name.' '.$this->last_name);
        return $this->nickname ?: 'Membre '.$this->id;
    }

    public function socialEvents() { return $this->hasMany(SocialEvent::class); }

    public function watchlist()
    {
        return $this->hasMany(\App\Models\WatchlistItem::class);
    }

    public function seriesFollows()
    {
        return $this->hasMany(SeriesFollow::class);
    }

    public function episodeAlerts()
    {
        return $this->hasMany(EpisodeAlert::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function lists()
    {
        return $this->hasMany(\App\Models\MediaList::class, 'user_id');
    }

    public function collaborativeLists()
    {
        return $this->hasManyThrough(
            \App\Models\MediaList::class,
            \App\Models\ListMember::class,
            'user_id',
            'id',
            'id',
            'list_id'
        );
    }

    public function favorites()
    {
        return $this->hasMany(\App\Models\Favorite::class, 'user_id');
    }

    /**
     * Abonnements aux plateformes (pivot user_platform_subscriptions)
     */
    public function platformSubscriptions(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Platform::class, 'user_platform_subscriptions')
            ->withPivot(['subscribed_via_platform_id', 'is_active', 'notify_opt_in'])
            ->withTimestamps();
    }
}
