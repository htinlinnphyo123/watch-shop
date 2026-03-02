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
        $specFields = [
            'watch_type', 'gender', 'movement', 'glass', 'water_resistant', 'shape', 
            'dial_size', 'dial_color', 'dial_markings', 'band', 'band_color', 'band_size',
            'lug_width', 'strap_buckle', 'case_material', 'case_color', 'case_thickness', 
            'case_finish', 'battery_type', 'couple'
        ];

        $specOptions = [];
        foreach ($specFields as $field) {
            $specOptions[$field] = Product::whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->pluck($field);
        }

        return Inertia::render('Products/Index', [
            'products' => Product::with(['brand', 'categories', 'customerGroups'])->paginate(10),
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'customer_groups' => CustomerGroup::all(),
            'specOptions' => $specOptions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'brand_id' => 'required|exists:brands,id',
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
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
            'is_featured' => 'boolean',
            'is_banner' => 'boolean',
            'is_admin_choice' => 'boolean',
            'special_discount' => 'boolean',
            'case_thickness' => 'nullable|string',
            'case_material' => 'nullable|string',
            'case_color' => 'nullable|string',
            'case_finish' => 'nullable|string',
            'dial_markings' => 'nullable|string',
            'lug_width' => 'nullable|string',
            'strap_buckle' => 'nullable|string',
            'battery_type' => 'nullable|string',
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

        $productData = collect($validated)->except(['customer_group_discounts', 'category_ids'])->toArray();
        $product = Product::create($productData);

        if ($request->has('category_ids')) {
            $product->categories()->sync($request->category_ids);
        }

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
            'category_ids' => 'required|array',
            'category_ids.*' => 'exists:categories,id',
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
            'is_featured' => 'boolean',
            'is_banner' => 'boolean',
            'is_admin_choice' => 'boolean',
            'special_discount' => 'boolean',
            'case_thickness' => 'nullable|string',
            'case_material' => 'nullable|string',
            'case_color' => 'nullable|string',
            'case_finish' => 'nullable|string',
            'dial_markings' => 'nullable|string',
            'lug_width' => 'nullable|string',
            'strap_buckle' => 'nullable|string',
            'battery_type' => 'nullable|string',
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

        $productData = collect($validated)->except(['customer_group_discounts', 'remove_images', 'category_ids'])->toArray();
        $product->update($productData);

        if ($request->has('category_ids')) {
            $product->categories()->sync($request->category_ids);
        }

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
            'product' => $product->load(['brand', 'categories', 'items']),
            'items' => $product->items,
        ]);
    }
}
