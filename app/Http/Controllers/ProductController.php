<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CustomerGroup;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => Product::with(['brand', 'category', 'customerGroups'])->paginate(10),
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'customer_groups' => CustomerGroup::all(),
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
            'currency' => 'nullable|in:MMK,USD,THB',
            'watch_type' => 'nullable|string',
            'glass' => 'nullable|string',
            'water_resistant' => 'nullable|string',
            'shape' => 'nullable|string',
            'couple' => 'nullable|string',
            'dial_size' => 'nullable|string',
            'dial_color' => 'nullable|string',
            'band' => 'nullable|string',
            'band_size' => 'nullable|string',
            'band_color' => 'nullable|string',
            'movement' => 'nullable|string',
            'gender' => 'nullable|string',
            'customer_group_discounts' => 'nullable|array',
            'customer_group_discounts.*.group_id' => 'required|exists:customer_groups,id',
            'customer_group_discounts.*.percentage' => 'nullable|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 'public');
        }
        
        if ($request->hasFile('images')) {
            $uploadedImages = [];
            foreach ($request->file('images') as $file) {
                $uploadedImages[] = $file->store('products/gallery', 'public');
            }
            $validated['images'] = $uploadedImages;
        }

        if (empty($validated['barcode'])) {
             $validated['barcode'] = 'W-' . strtoupper(uniqid());
        }

        $productData = collect($validated)->except('customer_group_discounts')->toArray();
        $product = Product::create($productData);

        if ($request->has('customer_group_discounts') && is_array($request->customer_group_discounts)) {
            $syncData = [];
            foreach ($request->customer_group_discounts as $discount) {
                if (isset($discount['percentage']) && $discount['percentage'] !== '') {
                    $syncData[$discount['group_id']] = ['percentage' => $discount['percentage']];
                }
            }
            $product->customerGroups()->sync($syncData);
        }

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
            'currency' => 'nullable|in:MMK,USD,THB',
            'watch_type' => 'nullable|string',
            'glass' => 'nullable|string',
            'water_resistant' => 'nullable|string',
            'shape' => 'nullable|string',
            'couple' => 'nullable|string',
            'dial_size' => 'nullable|string',
            'dial_color' => 'nullable|string',
            'band' => 'nullable|string',
            'band_size' => 'nullable|string',
            'band_color' => 'nullable|string',
            'movement' => 'nullable|string',
            'gender' => 'nullable|string',
            'customer_group_discounts' => 'nullable|array',
            'customer_group_discounts.*.group_id' => 'required|exists:customer_groups,id',
            'customer_group_discounts.*.percentage' => 'nullable|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 'public');
        }

        // Handle gallery images
        if ($request->hasFile('images')) {
            $uploadedImages = $product->images ? $product->images : [];
            foreach ($request->file('images') as $file) {
                $uploadedImages[] = $file->store('products/gallery', 'public');
            }
            $validated['images'] = $uploadedImages;
        }

        if ($request->has('remove_images')) {
            $uploadedImages = isset($validated['images']) ? $validated['images'] : ($product->images ?? []);
            foreach ($request->input('remove_images', []) as $imgToRemove) {
                if (($key = array_search($imgToRemove, $uploadedImages)) !== false) {
                    unset($uploadedImages[$key]);
                    Storage::disk('public')->delete($imgToRemove);
                }
            }
            $validated['images'] = array_values($uploadedImages);
        }

        $productData = collect($validated)->except(['customer_group_discounts', 'remove_images'])->toArray();
        $product->update($productData);

        if ($request->has('customer_group_discounts') && is_array($request->customer_group_discounts)) {
            $syncData = [];
            foreach ($request->customer_group_discounts as $discount) {
                if (isset($discount['percentage']) && $discount['percentage'] !== '') {
                    $syncData[$discount['group_id']] = ['percentage' => $discount['percentage']];
                }
            }
            $product->customerGroups()->sync($syncData);
        }

        return redirect()->back();
    }

    public function destroy(Product $product)
    {
        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        if ($product->images && is_array($product->images)) {
            foreach ($product->images as $img) {
                Storage::disk('public')->delete($img);
            }
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
