<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreOrderAttachment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['path', 'user_id', 'expires_at'];

    protected $casts = [
        'size' => 'integer',
        'expires_at' => 'datetime',
        'uploaded_at' => 'datetime',
    ];
}
