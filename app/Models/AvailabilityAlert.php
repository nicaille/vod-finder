<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityAlert extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['providers' => 'array', 'read_at' => 'datetime'];
    public function user() { return $this->belongsTo(User::class); }
    public function item() { return $this->belongsTo(WatchlistItem::class, 'watchlist_item_id'); }
    public function url(): string { return route('content.show', ['type' => $this->type, 'id' => $this->tmdb_id]); }
    public function providerLabel(): string { return collect($this->providers)->map(fn ($p) => $p['name'].(!empty($p['via']) ? ' '.\App\Support\ProviderIdentity::viaLabel($p['via']) : ''))->implode(', '); }
}
