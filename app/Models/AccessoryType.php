<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccessoryType extends Model
{
    protected $fillable = ['name', 'fields'];

    protected $casts = ['fields' => 'array'];
}
