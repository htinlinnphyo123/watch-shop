<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'specifications' => 'array',
        'images' => 'array',
        'is_featured' => 'boolean',
        'is_banner' => 'boolean',
        'is_admin_choice' => 'boolean',
        'special_discount' => 'boolean',
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
}
