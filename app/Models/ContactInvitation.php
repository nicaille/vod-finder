<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInvitation extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['token_hash'];
    protected $casts = ['expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime',
        'accepted_at' => 'immutable_datetime', 'email_sent_at' => 'immutable_datetime'];

    public function sender() { return $this->belongsTo(User::class, 'sender_id'); }
    public function usable(): bool { return !$this->revoked_at && !$this->accepted_at && $this->expires_at->isFuture(); }
    public function label(): string
    {
        return $this->revoked_at ? 'Révoquée' : ($this->accepted_at ? 'Acceptée' : (!$this->expires_at->isFuture() ? 'Expirée' : 'En attente'));
    }
}
