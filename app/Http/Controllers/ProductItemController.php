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
            'status' => 'required|in:available,sold,reserved,returned,lost,damaged',
        ]);

        $validated['system_unique_id'] = $this->generateUniqueSystemId();

        $product->items()->create($validated);

        return redirect()->back();
    }

    private function generateUniqueSystemId()
    {
        do {
            $id = '';
            for ($i = 0; $i < 12; $i++) {
                $id .= mt_rand(0, 9);
            }
        } while (ProductItem::where('system_unique_id', $id)->exists());

        return $id;
    }

    public function update(Request $request, ProductItem $item)
    {
        $validated = $request->validate([
            'serial_number' => 'required|unique:product_items,serial_number,' . $item->id,
            'status' => 'required|in:available,sold,reserved,returned,lost,damaged',
            'system_unique_id' => 'nullable|string|size:12|unique:product_items,system_unique_id,' . $item->id,
        ]);

        $item->update($validated);

        return redirect()->back();
    }

    public function destroy(ProductItem $item)
    {
        $item->delete();
        return redirect()->back();
    }
}
