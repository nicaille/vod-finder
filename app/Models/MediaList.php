<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaList extends Model
{
    protected $table = 'lists';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_public',
        'is_collaborative',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ListItem::class, 'list_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ListMember::class, 'list_id');
    }
}
