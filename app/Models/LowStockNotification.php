<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LowStockNotification extends Model
{
    use HasFactory;

    public const STATUSES = ['pending', 'processing', 'completed'];

    protected $guarded = [];

    protected $casts = [
        'stock_quantity' => 'integer',
        'priority_level' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
