<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = ['shop_price' => 'decimal:2', 'web_price' => 'decimal:2', 'discount_percentage' => 'decimal:2'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function soldItems()
    {
        // Historical relation name; includes this line's reserved units while pending.
        return $this->hasMany(ProductItem::class);
    }
}
