<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderAudit extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = ['snapshot' => 'array', 'changes' => 'array', 'created_at' => 'datetime', 'version' => 'integer'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Order history cannot be changed.'));
        static::deleting(fn () => throw new \LogicException('Order history cannot be deleted.'));
    }
}
