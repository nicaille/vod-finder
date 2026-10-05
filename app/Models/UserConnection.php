<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class UserConnection extends Model
{
    protected $guarded = ['id'];
    public function scopeForUser($q, int $id)
    {
        return $q->where(fn($q) => $q->where('user_low_id', $id)->orWhere('user_high_id', $id));
    }
    public function low()
    {
        return $this->belongsTo(User::class, 'user_low_id');
    }
    public function high()
    {
        return $this->belongsTo(User::class, 'user_high_id');
    }
    public function other(int $id): User
    {
        return $this->user_low_id === $id ? $this->high : $this->low;
    }
    public function involves(int $id): bool
    {
        return in_array($id, [$this->user_low_id, $this->user_high_id], true);
    }
}
