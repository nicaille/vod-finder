<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationMailSetting extends Model
{
    protected $fillable = ['brevo_enabled', 'brevo_api_key', 'sender_email', 'sender_name'];
    protected $hidden = ['brevo_api_key'];
    protected $casts = ['brevo_enabled' => 'boolean', 'brevo_api_key' => 'encrypted'];
}
