<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchImport extends Model
{
    protected $guarded = [];

    protected $casts = [
        'summary' => 'array',
        'errors' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
