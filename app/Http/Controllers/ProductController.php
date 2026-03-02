<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CustomerGroup;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $specFields = [
            'dial_size', 'dial_color', 'strap_size', 'strap_color', 'strap_material', 
            'strap_style', 'gender', 'movement', 'quick_release', 'clasp_type', 
            'origin', 'case_shape', 'water_resistant', 'crystal'
        ];

        $specOptions = [];
        foreach ($specFields as $field) {
            $specOptions[$field] = Product::whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->pluck($field);
        }

        $query = Product::with(['brand', 'categories', 'customerGroups'])
            ->withCount(['items as available_stock_count' => function ($q) {
                $q->where('status', 'available');
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('model_number', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        if ($request->filled('collection_id')) {
            $query->where('collection_id', $request->collection_id);
        }

        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        if ($request->filled('in_stock') && $request->in_stock === 'true') {
            $query->whereHas('items', function ($q) {
                $q->where('status', 'available');
            });
        }

        return Inertia::render('Products/Index', [
            'products' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'category_id', 'collection_id', 'min_price', 'max_price', 'in_stock']),
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'collections' => Collection::all(),
            'customer_groups' => CustomerGroup::all(),
            'specOptions' => $specOptions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required',
            'brand_id' => 'required|exists:brands,id',
            'collection_id' => 'nullable|exists:collections,id',
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
            'crystal' => 'nullable|string',
            'water_resistant' => 'nullable|string',
            'case_shape' => 'nullable|string',
            'dial_size' => 'nullable|string',
            'dial_color' => 'nullable|string',
            'strap_material' => 'nullable|string',
            'strap_size' => 'nullable|string',
            'strap_color' => 'nullable|string',
            'movement' => 'nullable|string',
            'gender' => 'nullable|string',
            'strap_style' => 'nullable|string',
            'quick_release' => 'nullable|string',
            'clasp_type' => 'nullable|string',
            'origin' => 'nullable|string',
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
            'collection_id' => 'nullable|exists:collections,id',
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
            'crystal' => 'nullable|string',
            'water_resistant' => 'nullable|string',
            'case_shape' => 'nullable|string',
            'dial_size' => 'nullable|string',
            'dial_color' => 'nullable|string',
            'strap_material' => 'nullable|string',
            'strap_size' => 'nullable|string',
            'strap_color' => 'nullable|string',
            'movement' => 'nullable|string',
            'gender' => 'nullable|string',
            'strap_style' => 'nullable|string',
            'quick_release' => 'nullable|string',
            'clasp_type' => 'nullable|string',
            'origin' => 'nullable|string',
            'customer_group_discounts' => 'nullable|array',
            'customer_group_discounts.*.group_id' => 'required|exists:customer_groups,id',
            'customer_group_discounts.*.percentage' => 'nullable|numeric|min:0|max:100',
            'images' => 'nullable|array',
            'images.*' => 'image|max:2048',
            'is_featured' => 'boolean',
            'is_banner' => 'boolean',
            'is_admin_choice' => 'boolean',
            'special_discount' => 'boolean',
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
