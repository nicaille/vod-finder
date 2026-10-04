<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListItem extends Model
{
    protected $fillable = [
        'list_id',
        'tmdb_id',
        'type',
        'added_by',
    ];

    public function list(): BelongsTo
    {
        return $this->belongsTo(MediaList::class, 'list_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
