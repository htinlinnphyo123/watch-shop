<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CustomerGroup;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $specFields = [
            'dial_size', 'dial_color', 'strap_size', 'strap_color', 'strap_material', 
            'strap_style', 'gender', 'movement', 'quick_release', 'clasp_type', 
            'origin', 'case_shape', 'water_resistant', 'crystal',
            'caliber_code', 'caseback_design',
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
            }])
            ->latest('updated_at');

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
            'web_price' => 'nullable|numeric',
            'discount' => 'nullable|numeric|min:0|max:100',
            'cost_price' => 'nullable|numeric',
            'model_number' => 'nullable|string',
            'warranty_period' => 'required|integer',
            'warranty_type' => 'nullable|in:international_warranty,shop_warranty',
            'description' => 'nullable|string',
            'image' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'preview_photo' => 'nullable',
            'preview_bg_photo' => 'nullable',
            'barcode' => 'nullable|string',
            'currency' => 'nullable|in:MMK,USD,THB',
            'crystal' => 'nullable|string',
            'caliber_code' => 'nullable|string',
            'caseback_design' => 'nullable|string',
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
            'is_featured' => 'boolean',
            'is_banner' => 'boolean',
            'is_limited_collection' => 'boolean',
            'is_latest' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->image)) {
            $validated['image'] = $request->image;
        }

        if ($request->hasFile('preview_photo')) {
            $validated['preview_photo'] = $request->file('preview_photo')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->preview_photo)) {
            $validated['preview_photo'] = $request->preview_photo;
        }

        if ($request->hasFile('preview_bg_photo')) {
            $validated['preview_bg_photo'] = $request->file('preview_bg_photo')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->preview_bg_photo)) {
            $validated['preview_bg_photo'] = $request->preview_bg_photo;
        }
        
        if ($request->has('images')) {
            $uploadedImages = [];
            foreach ($request->images as $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile) {
                    $uploadedImages[] = $item->store('products/gallery', env('FILESYSTEM_DISK', 's3'));
                } elseif (is_string($item)) {
                    $uploadedImages[] = $item;
                }
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
            'web_price' => 'nullable|numeric',
            'discount' => 'nullable|numeric|min:0|max:100',
            'cost_price' => 'nullable|numeric',
            'model_number' => 'nullable|string',
            'warranty_period' => 'required|integer',
            'warranty_type' => 'nullable|in:international_warranty,shop_warranty',
            'description' => 'nullable|string',
            'image' => 'nullable',
            'preview_photo' => 'nullable',
            'preview_bg_photo' => 'nullable',
            'barcode' => 'nullable|string',
            'currency' => 'nullable|in:MMK,USD,THB',
            'crystal' => 'nullable|string',
            'caliber_code' => 'nullable|string',
            'caseback_design' => 'nullable|string',
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
            'images.*' => 'nullable',
            'is_featured' => 'boolean',
            'is_banner' => 'boolean',
            'is_limited_collection' => 'boolean',
            'is_latest' => 'boolean',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
        ]);

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->image) && trim($request->image) !== '') {
            $validated['image'] = $request->image;
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('preview_photo')) {
            if ($product->preview_photo) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($product->preview_photo);
            }
            $validated['preview_photo'] = $request->file('preview_photo')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->preview_photo) && trim($request->preview_photo) !== '') {
            $validated['preview_photo'] = $request->preview_photo;
        } else {
            unset($validated['preview_photo']);
        }

        if ($request->hasFile('preview_bg_photo')) {
            if ($product->preview_bg_photo) {
                Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($product->preview_bg_photo);
            }
            $validated['preview_bg_photo'] = $request->file('preview_bg_photo')->store('products', env('FILESYSTEM_DISK', 's3'));
        } elseif (is_string($request->preview_bg_photo) && trim($request->preview_bg_photo) !== '') {
            $validated['preview_bg_photo'] = $request->preview_bg_photo;
        } else {
            unset($validated['preview_bg_photo']);
        }

        // Handle gallery images
        $uploadedImages = $product->images ? $product->images : [];
        if ($request->has('images')) {
            foreach ($request->images as $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile) {
                    $uploadedImages[] = $item->store('products/gallery', env('FILESYSTEM_DISK', 's3'));
                } elseif (is_string($item) && !in_array($item, $uploadedImages)) {
                    $uploadedImages[] = $item;
                }
            }
        }
        $validated['images'] = $uploadedImages;

        if ($request->has('remove_images')) {
            $uploadedImages = isset($validated['images']) ? $validated['images'] : ($product->images ?? []);
            foreach ($request->input('remove_images', []) as $imgToRemove) {
                if (($key = array_search($imgToRemove, $uploadedImages)) !== false) {
                    unset($uploadedImages[$key]);
                    Storage::disk(env('FILESYSTEM_DISK', 's3'))->delete($imgToRemove);
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
        // Soft delete — do NOT remove images so the product can be restored later.
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

    public function export()
    {
        $products = Product::with(['brand', 'collection', 'categories'])->withCount('items')->get();
        
        $date = Carbon::now()->format('Y-m-d_H-i-s');

        return (new \Rap2hpoutre\FastExcel\FastExcel($products))->download('products-'.$date.'.xlsx', function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'model_number' => $product->model_number,
                'price' => $product->price,
                'cost_price' => $product->cost_price,
                'web_price' => $product->web_price,
                'discount' => $product->discount,
                'warranty_period' => $product->warranty_period,
                'warranty_type' => $product->warranty_type,
                'description' => $product->description,
                'currency' => $product->currency,
                'crystal' => $product->crystal,
                'water_resistant' => $product->water_resistant,
                'case_shape' => $product->case_shape,
                'dial_size' => $product->dial_size,
                'dial_color' => $product->dial_color,
                'strap_material' => $product->strap_material,
                'strap_size' => $product->strap_size,
                'strap_color' => $product->strap_color,
                'movement' => $product->movement,
                'gender' => $product->gender,
                'clasp_type' => $product->clasp_type,
                'strap_style' => $product->strap_style,
                'quick_release' => $product->quick_release,
                'origin' => $product->origin,
                'caliber_code' => $product->caliber_code,
                'caseback_design' => $product->caseback_design,
                'brand' => optional($product->brand)->name,
                'collection' => optional($product->collection)->name,
                'categories' => $product->categories ? $product->categories->pluck('name')->implode(', ') : null,
                'items_count' => $product->items_count ?? 0,
                'is_featured' => (int) $product->is_featured,
                'is_banner' => (int) $product->is_banner,
                'is_limited_collection' => (int) $product->is_limited_collection,
                'is_latest' => (int) $product->is_latest,
                'is_active' => (int) $product->is_active,
                'is_public' => (int) $product->is_public,
            ];
        });
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $errors = [];
        
        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $collection = (new \Rap2hpoutre\FastExcel\FastExcel)->import($request->file('file'));


            $brandsCache = Brand::pluck('id', 'name')->toArray();
            $collectionsCache = Collection::pluck('id', 'name')->toArray();
            $categoriesCache = Category::pluck('id', 'name')->toArray();

            foreach ($collection as $index => $line) {
                // Determine Excel row (index starts at 0 for row 2, since 1 is headers)
                $rowNum = $index + 2;
                $initialRowErrors = count($errors);

                unset($line['specifications']);

                // Parse Brand
                if (!empty($line['brand'])) {
                    if (array_key_exists($line['brand'], $brandsCache)) {
                        $line['brand_id'] = $brandsCache[$line['brand']];
                    } else {
                        $errors[] = "Row {$rowNum}, Column 'brand': Brand '{$line['brand']}' does not exist.";
                    }
                } else {
                    $errors[] = "Row {$rowNum}, Column 'brand': Brand is required.";
                }
                unset($line['brand']);

                // Parse Collection
                if (!empty($line['collection'])) {
                    if (array_key_exists($line['collection'], $collectionsCache)) {
                        $line['collection_id'] = $collectionsCache[$line['collection']];
                    } else {
                        $errors[] = "Row {$rowNum}, Column 'collection': Collection '{$line['collection']}' does not exist.";
                    }
                }
                unset($line['collection']);

                // Parse Categories
                $categoryIdsRaw = [];
                if (!empty($line['categories'])) {
                    $catNames = array_map('trim', explode(',', $line['categories']));
                    foreach ($catNames as $catName) {
                        if (array_key_exists($catName, $categoriesCache)) {
                            $categoryIdsRaw[] = $categoriesCache[$catName];
                        } else {
                            $errors[] = "Row {$rowNum}, Column 'categories': Category '{$catName}' does not exist.";
                        }
                    }
                }
                unset($line['categories']);

                $requestedItemsCount = isset($line['items_count']) ? (int) $line['items_count'] : 0;
                unset($line['items_count']); 

                $validator = Validator::make($line, [
                    'name' => 'required|string',
                    'price' => 'required|numeric',
                    'cost_price' => 'nullable|numeric',
                    'web_price' => 'nullable|numeric',
                    'discount' => 'nullable|numeric|min:0|max:100',
                    'warranty_period' => 'nullable|integer',
                    'warranty_type' => 'nullable|in:international_warranty,shop_warranty',
                ]);

                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $column => $messages) {
                        foreach ($messages as $msg) {
                            $errors[] = "Row {$rowNum}, Column '{$column}': {$msg}";
                        }
                    }
                }

                if (count($errors) > $initialRowErrors) {
                    continue; // Skip DB operations for this row if there were any validation errors
                }

                if (!empty($line['id'])) {
                    // Update
                    $product = Product::find($line['id']);
                    if (!$product) {
                        $errors[] = "Row {$rowNum}, Column 'id': Product ID {$line['id']} not found.";
                        continue;
                    }

                    $currentItemsCount = $product->items()->count();

                    if ($requestedItemsCount < $currentItemsCount) {
                        $errors[] = "Row {$rowNum}, Column 'items_count': items_count ({$requestedItemsCount}) cannot be less than original count ({$currentItemsCount}).";
                    } elseif ($requestedItemsCount > $currentItemsCount) {
                        $diff = $requestedItemsCount - $currentItemsCount;
                        $this->generateProductItems($product, $diff);
                    }

                    // Remove image lines from import mapping to protect web updates
                    unset($line['image'], $line['preview_photo'], $line['preview_bg_photo'], $line['images']);

                    $product->update($line);
                    
                    if (!empty($categoryIdsRaw)) {
                        $product->categories()->sync($categoryIdsRaw);
                    } else {
                        $product->categories()->detach();
                    }
                } else {
                    // Create
                    unset($line['id']);
                    
                    // Remove image lines from import mapping to protect web updates
                    unset($line['image'], $line['preview_photo'], $line['preview_bg_photo'], $line['images']);

                    if (empty($line['barcode'])) {
                        $line['barcode'] = 'W-' . strtoupper(uniqid());
                    }

                    $product = Product::create($line);

                    if (!empty($categoryIdsRaw)) {
                        $product->categories()->sync($categoryIdsRaw);
                    }

                    if ($requestedItemsCount > 0) {
                        $this->generateProductItems($product, $requestedItemsCount);
                    }
                }
            }

            if (count($errors) > 0) {
                \Illuminate\Support\Facades\DB::rollBack();
                return redirect()->back()->with('import_errors', $errors);
            }

            \Illuminate\Support\Facades\DB::commit();

        } catch (\Illuminate\Database\QueryException $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            $errorMessage = $e->getMessage();
            dd($errorMessage);
            
            // Try to make Postgres numeric errors more readable as a fallback
            if (strpos($errorMessage, 'invalid input syntax for type numeric') !== false) {
                return redirect()->back()->with('import_errors', ["Database Error: A numeric column received invalid text. Please check that price, discount, and other numeric fields contain only numbers."]);
            }
            
            return redirect()->back()->with('import_errors', ["Database Error: " . $errorMessage]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->with('import_errors', ["System Error: " . $e->getMessage()]);
        }

        return redirect()->back()->with('success', 'Products imported completely without errors.');
    }

    private function generateProductItems(Product $product, int $quantity)
    {
        for ($i = 0; $i < $quantity; $i++) {
            $product->items()->create([
                'serial_number'    => null,
                'system_unique_id' => $this->generateUniqueSystemId(),
                'status'           => 'available',
            ]);
        }
    }

    private function generateUniqueSystemId(): string
    {
        do {
            $id = '';
            for ($i = 0; $i < 12; $i++) {
                $id .= mt_rand(0, 9);
            }
        } while (\App\Models\ProductItem::where('system_unique_id', $id)->exists());

        return $id;
    }

    public function presignedUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'contentType' => 'required|string',
        ]);

        $path = 'products/' . uniqid() . '_' . $request->filename;

        // Uses AWS S3 adapter to generate a pre-signed url for client upload
        $uploadData = Storage::disk(env('FILESYSTEM_DISK', 's3'))
                 ->temporaryUploadUrl($path, now()->addMinutes(10), [
                     'ContentType' => $request->contentType,
                     'ACL' => 'public-read'
                 ]);

        $url = is_array($uploadData) ? $uploadData['url'] : $uploadData;
        $headers = is_array($uploadData) && isset($uploadData['headers']) ? $uploadData['headers'] : [];

        return response()->json([
            'url' => $url,
            'headers' => $headers,
            'path' => $path
        ]);
    }
}
