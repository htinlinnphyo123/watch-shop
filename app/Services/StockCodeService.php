<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductItem;

class StockCodeService
{
    public function generate(): string
    {
        do {
            // Keep a fixed width, including leading zeroes, for Code 128 labels.
            $code = str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        } while (
            ProductItem::withTrashed()->where('system_unique_id', $code)->orWhere('serial_number', $code)->exists()
            || Product::withTrashed()->where('barcode', $code)->exists()
        );

        return $code;
    }
}
