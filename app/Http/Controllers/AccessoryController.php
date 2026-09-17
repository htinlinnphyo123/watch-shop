<?php

namespace App\Http\Controllers;

use App\Models\AccessoryType;
use App\Models\Product;
use App\Services\StockCodeService;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AccessoryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:100', 'type' => 'nullable|integer',
            'status' => 'nullable|in:active,inactive', 'stock' => 'nullable|in:available,out',
        ]);
        $query = Product::where('kind', 'accessory')->with('accessoryType')->withItemCounts();
        $query->when($request->filled('search'), fn ($q) => $q->where(fn ($search) => $search->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($request->search).'%'])->orWhereRaw('LOWER(barcode) LIKE ?', ['%'.mb_strtolower($request->search).'%'])));
        $query->when($request->filled('type'), fn ($q) => $q->where('accessory_type_id', $request->type));

        $query->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'));
        if ($request->stock === 'available') {
            $query->whereHas('items', fn ($q) => $q->where('status', 'available'));
        } elseif ($request->stock === 'out') {
            $query->whereDoesntHave('items', fn ($q) => $q->where('status', 'available'));
        }
        $all = Product::where('kind', 'accessory');

        return Inertia::render('Accessories/Index', [
            'summary' => [
                'total' => (clone $all)->count(),
                'active' => (clone $all)->where('is_active', true)->count(),
                'out_of_stock' => (clone $all)->whereDoesntHave('items', fn ($q) => $q->where('status', 'available'))->count(),
                'available_units' => \App\Models\ProductItem::where('status', 'available')->whereHas('product', fn ($q) => $q->where('kind', 'accessory'))->count(),
            ],
            'accessories' => $query->latest()->paginate(20)->withQueryString(),
            'types' => AccessoryType::orderBy('name')->get(),
            'filters' => $request->only('search', 'type', 'status', 'stock'),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, Product $accessory)
    {
        abort_unless($accessory->kind === 'accessory', 404);

        return $this->save($request, $accessory);
    }

    private function save(Request $request, ?Product $accessory = null)
    {
        $request->merge(['images' => $request->input('images', []), 'accessory_attributes' => $request->input('accessory_attributes', [])]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'accessory_type_id' => 'required|exists:accessory_types,id',
            'price' => 'required|numeric|min:0|max:9999999999.99',
            'currency' => 'required|in:MMK,USD,THB,SGD,CNY',
            'barcode' => ['nullable', 'string', 'max:255', Rule::unique('products', 'barcode')->ignore($accessory?->id)],
            'description' => 'nullable|string|max:10000',
            'is_active' => 'required|boolean',
            'is_public' => 'required|boolean',
            'accessory_attributes' => 'present|array|max:30',
            'accessory_attributes.*' => 'array:name,value',
            'accessory_attributes.*.name' => 'required|string|max:80|distinct:ignore_case',
            'accessory_attributes.*.value' => 'required|string|max:255',
            'images' => 'present|array|max:10',
            'images.*' => 'string|max:2048',
            'uploads' => 'sometimes|array|max:10',
            'uploads.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);
        // Existing keys may only be retained from this accessory's own gallery.
        foreach ($data['images'] as $key) {
            abort_unless(in_array($key, $accessory?->images ?? [], true), 422, 'Invalid image.');
        }
        if (count($data['images']) + count($request->file('uploads', [])) > 10) {
            throw \Illuminate\Validation\ValidationException::withMessages(['uploads' => 'Use at most 10 images.']);
        }
        unset($data['uploads']);
        foreach ($request->file('uploads', []) as $file) {
            $data['images'][] = $file->store('accessories', 's3');
        }
        $data['kind'] = 'accessory';
        if (empty($data['barcode'])) {
            $data['barcode'] = $accessory?->barcode ?: 'A-'.Str::ulid();
        }
        if ($accessory) {
            $accessory->update($data);
        } else {
            Product::create($data + ['warranty_period' => 0]);
        }

        return back()->with('success', 'Accessory saved.');
    }

    public function stock(Request $request, Product $accessory)
    {
        abort_unless($accessory->kind === 'accessory', 404);
        $data = $request->validate(['quantity' => 'required|integer|min:1|max:500', 'operation' => 'required|in:add,remove']);
        DB::transaction(function () use ($accessory, $data) {
            Product::whereKey($accessory->id)->lockForUpdate()->firstOrFail();
            if ($data['operation'] === 'add') {
                for ($i = 0; $i < $data['quantity']; $i++) {
                    $accessory->items()->create(['status' => 'available', 'system_unique_id' => app(StockCodeService::class)->generate()]);
                }
            } else {
                $items = $accessory->items()->where('status', 'available')->lockForUpdate()->limit($data['quantity'])->get();
                if ($items->count() < $data['quantity']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['quantity' => 'Cannot remove more than the available stock.']);
                }
                foreach ($items as $item) {
                    $item->delete();
                }
            }
        });
        app(\App\Services\LowStockNotificationService::class)->sync($accessory);

        return back()->with('success', 'Stock updated.');
    }

    public function labels(Product $accessory)
    {
        abort_unless($accessory->kind === 'accessory', 404);

        $items = DB::transaction(function () use ($accessory) {
            Product::whereKey($accessory->id)->lockForUpdate()->firstOrFail();
            $items = $accessory->items()->where('status', 'available')->orderBy('id')->lockForUpdate()->get();
            foreach ($items as $item) {
                if (! $item->system_unique_id) {
                    $item->update(['system_unique_id' => app(StockCodeService::class)->generate()]);
                }
            }

            return $items;
        });

        return response()->json(['items' => $items->map(fn ($item) => $item->only(['id', 'system_unique_id']))]);
    }

    public function saveType(Request $request, ?AccessoryType $type = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('accessory_types')->ignore($type?->id)],
            'fields' => 'present|array|max:30',
            'fields.*' => 'required|string|max:80|distinct:ignore_case',
        ]);
        $type ? $type->update($data) : AccessoryType::create($data);

        return back()->with('success', 'Accessory type saved.');
    }
}
