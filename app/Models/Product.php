<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $casts = [
        'specifications' => 'array',
        'images' => 'array',
        'is_featured' => 'boolean',
        'is_banner' => 'boolean',
        'is_limited_collection' => 'boolean',
        'is_latest' => 'boolean',
        'priority_level' => 'integer',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    public function items()
    {
        return $this->hasMany(ProductItem::class);
    }

    public function customerGroups()
    {
        return $this->belongsToMany(CustomerGroup::class)->withPivot('percentage')->withTimestamps();
    }

    public function lowStockNotifications()
    {
        return $this->hasMany(LowStockNotification::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function scopeWithItemCounts($query)
    {
        return $query->withCount([
            'items as total_items',
            'items as available_items' => function ($q) {
                $q->where('status', 'available');
            },
            'items as sold_items' => function ($q) {
                $q->where('status', 'sold');
            },
            'items as reserved_items' => function ($q) {
                $q->where('status', 'reserved');
            },
        ]);
    }
}
