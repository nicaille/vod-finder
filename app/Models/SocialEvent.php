<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class SocialEvent extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['read_at' => 'datetime'];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
    public function recommendation()
    {
        return $this->belongsTo(Recommendation::class);
    }
    public function connection()
    {
        return $this->belongsTo(UserConnection::class, 'user_connection_id');
    }
    public function url(): string
    {
        return $this->recommendation_id ? route('recommendations.show', $this->recommendation_id) : route('contacts.index');
    }
    public function label(): string
    {
        return match ($this->kind) {
            'recommendation' => $this->actor->socialName() . ' te recommande ' . ($this->recommendation?->content['title'] ?? 'un titre'),
            'accepted' => $this->actor->socialName() . ' a accepté ta demande',
            default => $this->actor->socialName() . ' souhaite te rejoindre',
        };
    }
}
