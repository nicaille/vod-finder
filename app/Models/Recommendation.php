<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class Recommendation extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['content' => 'array', 'read_at' => 'datetime', 'archived_at' => 'datetime'];
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
