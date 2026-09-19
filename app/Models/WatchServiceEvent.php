<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WatchServiceEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    protected $casts = ['repair_details' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Service history cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Service history cannot be deleted.'));
    }
}
