<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class WatchValidation
{
    public static function rules(?int $productId = null): array
    {
        return [
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:9999999999.99',
            'web_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'cost_price' => 'nullable|numeric|min:0|max:9999999999.99',
            'discount' => 'nullable|numeric|min:0|max:100',
            'warranty_period' => 'nullable|integer|min:0|max:1200',
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($productId)],
            'model_number' => 'nullable|string|max:255',
        ];
    }
}
