<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => Product::with(['brand', 'category'])->paginate(10),
            'brands' => Brand::all(),
            'categories' => Category::all(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric',
            'cost_price' => 'nullable|numeric',
            'model_number' => 'nullable|string',
            'warranty_period' => 'required|integer',
            'description' => 'nullable|string',
            'image' => 'nullable|image',
            'barcode' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }
        
        if (empty($validated['barcode'])) {
             $validated['barcode'] = 'W-' . strtoupper(uniqid());
        }

        Product::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'name' => 'required',
            'brand_id' => 'required|exists:brands,id',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric',
            'cost_price' => 'nullable|numeric',
            'model_number' => 'nullable|string',
            'warranty_period' => 'required|integer',
            'description' => 'nullable|string',
            'image' => 'nullable|image',
            'barcode' => 'nullable|string',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($validated);

        return redirect()->back();
    }

    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();
        return redirect()->back();
    }
    
    public function show(Product $product)
    {
        return Inertia::render('Products/Show', [
            'product' => $product->load(['brand', 'category', 'items']),
            'items' => $product->items,
        ]);
    }
}
