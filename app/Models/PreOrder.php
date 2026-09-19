<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PreOrder extends Model
{
    protected $fillable = ['customer_id', 'brand_id', 'watch_details', 'amount_paid', 'remark', 'user_id', 'product_id', 'status', 'type', 'product_item_id'];

    protected $casts = ['amount_paid' => 'decimal:2'];

    public function customer()
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    public function product()
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function reservedItem()
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id')->withTrashed();
    }

    public function fileUploads()
    {
        return $this->hasMany(PreOrderAttachment::class)->whereNotNull('uploaded_at');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
