<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskRun extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    
}
