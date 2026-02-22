<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Http\Request;

class ProductItemController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $validated = $request->validate([
            'serial_number' => 'required|unique:product_items,serial_number',
            'status' => 'required|in:available,sold,reserved',
        ]);

        $product->items()->create($validated);

        return redirect()->back();
    }

    public function destroy(ProductItem $item)
    {
        $item->delete();
        return redirect()->back();
    }
}
