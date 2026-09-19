<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\CustomerGroup;
use App\Models\Product;
use App\Models\Setting;
use App\Models\WatchImport;
use App\Jobs\ImportWatchesFromSpreadsheet;
use App\Services\LowStockNotificationService;
use App\Services\WatchSpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private readonly LowStockNotificationService $lowStockNotifications,
        private readonly WatchSpreadsheetService $watchSpreadsheets,
    ) {}

    public function index(Request $request)
    {
        $specFields = [
            'dial_size', 'dial_color', 'strap_size', 'strap_color', 'strap_material',
            'strap_style', 'gender', 'movement', 'quick_release', 'clasp_type',
            'origin', 'case_shape', 'water_resistant', 'crystal',
            'caliber_code', 'caseback_design', 'case_material',
        ];

        $specOptions = [];
        foreach ($specFields as $field) {
            $specOptions[$field] = Product::whereNotNull($field)
                ->where($field, '!=', '')
                ->distinct()
                ->pluck($field);
        }

        $query = Product::where('kind', 'watch')->with(['brand', 'categories', 'customerGroups'])
            ->withCount(['items as available_stock_count' => function ($q) {
                $q->where('status', 'available');
            }]);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('model_number', 'ilike', "%{$search}%")
                    ->orWhere('barcode', 'ilike', "%{$search}%");
            });
        }

        if ($request->filled('category_id')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category_id);
            });
        }

        if ($request->filled('brand_id')) {
            $query->where('brand_id', $request->brand_id);
        }

        // Load exchange rates once for price filtering and sorting
        $rates = Setting::whereIn('key', ['usd_rate', 'thb_rate', 'sgd_rate', 'cny_rate'])
            ->pluck('value', 'key');
        $usdRate = (float) ($rates['usd_rate'] ?? 1);
        $thbRate = (float) ($rates['thb_rate'] ?? 1);
        $sgdRate = (float) ($rates['sgd_rate'] ?? 1);
        $cnyRate = (float) ($rates['cny_rate'] ?? 1);

        // Price filtering: filter by MMK-equivalent price
        $mmkPriceExpression = "
            CASE
                WHEN currency = 'MMK' THEN price
                WHEN currency = 'USD' THEN price * {$usdRate}
                WHEN currency = 'THB' THEN price * {$thbRate}
                WHEN currency = 'SGD' THEN price * {$sgdRate}
                WHEN currency = 'CNY' THEN price * {$cnyRate}
                ELSE price
            END
        ";

        if ($request->filled('min_price')) {
            $query->whereRaw("({$mmkPriceExpression}) >= ?", [$request->min_price]);
        }

        if ($request->filled('max_price')) {
            $query->whereRaw("({$mmkPriceExpression}) <= ?", [$request->max_price]);
        }

        if ($request->filled('in_stock') && $request->in_stock === 'true') {
            $query->whereHas('items', function ($q) {
                $q->where('status', 'available');
            });
        }

        // Sorting
        $sortField = $request->get('sort', 'updated_at');
        $sortDirection = $request->get('direction', 'desc');
        $allowedSorts = ['name', 'price', 'updated_at'];
        if (in_array($sortField, $allowedSorts)) {
            if ($sortField === 'price') {
                $direction = $sortDirection === 'asc' ? 'asc' : 'desc';
                $query->orderByRaw("({$mmkPriceExpression}) {$direction}");
            } else {
                $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
            }
        }

        $latestImport = null;
        if ($request->user()->role === 'admin') {
            $latestImport = WatchImport::where('started_by', $request->user()->id)->latest('id')->first();
            if ($latestImport?->dismissed_at) {
                $latestImport = null;
            }
        }

        return Inertia::render('Products/Index', [
            'products' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'category_id', 'brand_id', 'min_price', 'max_price', 'in_stock', 'sort', 'direction']),
            'brands' => Brand::all(),
            'categories' => Category::all(),
            'collections' => Collection::all(),
            'customer_groups' => CustomerGroup::all(),
            'specOptions' => $specOptions,
            'userRole' => $request->user()->role ?? 'user',
            'latestImport' => $latestImport,
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
            'youtube_link' => 'nullable|url:http,https|max:2048',
            'case_material' => 'nullable|string|max:255',
            'priority_level' => 'nullable|integer|in:0,1,2,3',
            'image' => 'nullable',
            'images' => 'nullable|array',
            'images.*' => 'nullable',
            'preview_photo' => 'nullable',
            'preview_bg_photo' => 'nullable',
            'barcode' => 'nullable|string',
            'currency' => 'nullable|in:MMK,USD,THB,SGD,CNY',
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

        if ($request->user()->role !== 'admin') {
            unset($validated['cost_price']);
        }

        $validated['priority_level'] = $validated['priority_level'] ?? 0;

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('products', 's3');
        } elseif (is_string($request->image)) {
            $validated['image'] = $request->image;
        }

        if ($request->hasFile('preview_photo')) {
            $validated['preview_photo'] = $request->file('preview_photo')->store('products', 's3');
        } elseif (is_string($request->preview_photo)) {
            $validated['preview_photo'] = $request->preview_photo;
        }

        if ($request->hasFile('preview_bg_photo')) {
            $validated['preview_bg_photo'] = $request->file('preview_bg_photo')->store('products', 's3');
        } elseif (is_string($request->preview_bg_photo)) {
            $validated['preview_bg_photo'] = $request->preview_bg_photo;
        }

        if ($request->has('images')) {
            $uploadedImages = [];
            foreach ($request->images as $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile) {
                    $uploadedImages[] = $item->store('products/gallery', 's3');
                } elseif (is_string($item)) {
                    $uploadedImages[] = $item;
                }
            }
            $validated['images'] = $uploadedImages;
        }

        if (empty($validated['barcode'])) {
            $validated['barcode'] = 'W-'.strtoupper(uniqid());
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

        $this->lowStockNotifications->sync($product);

        return redirect()->back();
    }

    public function update(Request $request, Product $product)
    {
        abort_unless($product->kind === 'watch', 404);
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
            'youtube_link' => 'nullable|url:http,https|max:2048',
            'case_material' => 'nullable|string|max:255',
            'priority_level' => 'nullable|integer|in:0,1,2,3',
            'image' => 'nullable',
            'preview_photo' => 'nullable',
            'preview_bg_photo' => 'nullable',
            'barcode' => 'nullable|string',
            'currency' => 'nullable|in:MMK,USD,THB,SGD,CNY',
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

        if ($request->user()->role !== 'admin') {
            unset($validated['cost_price']);
        }

        $validated['priority_level'] = $validated['priority_level'] ?? 0;

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('s3')->delete($product->image);
            }
            $validated['image'] = $request->file('image')->store('products', 's3');
        } elseif (is_string($request->image) && trim($request->image) !== '') {
            $validated['image'] = $request->image;
        } else {
            unset($validated['image']);
        }

        if ($request->hasFile('preview_photo')) {
            if ($product->preview_photo) {
                Storage::disk('s3')->delete($product->preview_photo);
            }
            $validated['preview_photo'] = $request->file('preview_photo')->store('products', 's3');
        } elseif (is_string($request->preview_photo) && trim($request->preview_photo) !== '') {
            $validated['preview_photo'] = $request->preview_photo;
        } else {
            unset($validated['preview_photo']);
        }

        if ($request->hasFile('preview_bg_photo')) {
            if ($product->preview_bg_photo) {
                Storage::disk('s3')->delete($product->preview_bg_photo);
            }
            $validated['preview_bg_photo'] = $request->file('preview_bg_photo')->store('products', 's3');
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
                    $uploadedImages[] = $item->store('products/gallery', 's3');
                } elseif (is_string($item) && ! in_array($item, $uploadedImages)) {
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
                    Storage::disk('s3')->delete($imgToRemove);
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

        $this->lowStockNotifications->sync($product);

        return redirect()->back();
    }

    public function destroy(Product $product)
    {
        abort_unless($product->kind === 'watch', 404);
        // Soft delete — do NOT remove images so the product can be restored later.
        $product->delete();

        return redirect()->back();
    }

    public function show(Request $request, Product $product)
    {
        if ($product->kind === 'accessory') {
            return redirect()->route('accessories.show', $product);
        }

        abort_unless($product->kind === 'watch', 404);
        $filters = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:available,sold,reserved,returned,lost,damaged',
        ]);
        $counts = $product->items()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $items = $product->items()
            ->with('orderItem:id,order_id')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $filters['status']))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($search) => $search
                ->where('system_unique_id', 'like', '%'.$filters['search'].'%')
                ->orWhereRaw('LOWER(serial_number) LIKE ?', ['%'.mb_strtolower($filters['search']).'%'])))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Products/Show', [
            'product' => $product->load(['brand', 'categories']),
            'items' => $items,
            'counts' => $counts,
            'filters' => $filters,
        ]);
    }

    public function export()
    {
        $watches = Product::where('kind', 'watch')
            ->with(['brand', 'collection', 'categories'])
            ->withItemCounts()
            ->orderBy('id')
            ->get();

        $date = Carbon::now()->format('Y-m-d_H-i-s');

        return (new \Rap2hpoutre\FastExcel\FastExcel($watches))
            ->download('watches-'.$date.'.xlsx', fn (Product $watch) => $this->watchSpreadsheets->exportRow($watch));
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240|extensions:xlsx,csv',
        ]);

        $file = $request->file('file');
        $import = WatchImport::create([
            'started_by' => $request->user()->id,
            'original_name' => substr(basename($file->getClientOriginalName()), 0, 255),
            'file_path' => '',
            'status' => 'queued',
        ]);
        $path = $file->storeAs('watch-imports', $import->id.'.'.$file->extension(), 'local');
        if (! $path) {
            $import->update(['status' => 'failed', 'failure_message' => 'The spreadsheet could not be saved for processing.', 'finished_at' => now()]);

            return back()->with('error', 'The spreadsheet could not be saved for processing. Please try again.');
        }
        $import->update(['file_path' => $path]);

        try {
            ImportWatchesFromSpreadsheet::dispatch($import->id);
        } catch (\Throwable $exception) {
            report($exception);
            Storage::disk('local')->delete($path);
            $import->update(['status' => 'failed', 'failure_message' => 'The import could not be added to the queue. Please try again or contact support.', 'finished_at' => now()]);

            return back()->with('error', 'The import could not be queued. Please try again.');
        }

        return back()->with('success', 'Watch import queued. You can follow its status and any row errors on this page.');
    }

    public function importStatus(Request $request, WatchImport $watchImport)
    {
        abort_unless($request->user()->role === 'admin' && $watchImport->started_by === $request->user()->id, 404);

        return response()->json($watchImport->only([
            'id', 'original_name', 'status', 'processed_rows', 'total_rows',
            'summary', 'errors', 'failure_message', 'created_at', 'finished_at',
        ]));
    }

    public function dismissImport(Request $request, WatchImport $watchImport)
    {
        abort_unless($request->user()->role === 'admin' && $watchImport->started_by === $request->user()->id, 404);

        $watchImport->update(['dismissed_at' => now()]);

        return response()->noContent();
    }

    public function presignedUrl(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'contentType' => 'required|string',
        ]);

        $path = 'products/'.uniqid().'_'.$request->filename;

        // Uses AWS S3 adapter to generate a pre-signed url for client upload
        $uploadData = Storage::disk('s3')
            ->temporaryUploadUrl($path, now()->addMinutes(10), [
                'ContentType' => $request->contentType,
                'ACL' => 'public-read',
            ]);

        $url = is_array($uploadData) ? $uploadData['url'] : $uploadData;
        $headers = is_array($uploadData) && isset($uploadData['headers']) ? $uploadData['headers'] : [];

        return response()->json([
            'url' => $url,
            'headers' => $headers,
            'path' => $path,
        ]);
    }
}
