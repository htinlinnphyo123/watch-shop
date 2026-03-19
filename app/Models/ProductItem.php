<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductItem extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'system_unique_id' => 'string',
        'serial_number'    => 'string',
        'purchase_date'    => 'date',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
