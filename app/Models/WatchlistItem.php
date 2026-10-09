<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WatchlistItem extends Model
{
    use HasFactory;

    protected $casts = ['availability_providers' => 'array', 'availability_checked_at' => 'datetime'];

    protected $fillable = [
        'user_id',
        'tmdb_id',
        'type',
        'title',
        'poster',
        'year',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
