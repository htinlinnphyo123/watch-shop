<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NewProductResource;
use App\Models\AccessoryType;
use App\Models\Product;
use Illuminate\Http\Request;

class AccessoryController extends Controller
{
    private function catalog(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:100', 'type' => 'nullable|integer']);

        return Product::where('kind', 'accessory')->where('is_active', true)->where('is_public', true)
            ->with(['accessoryType', 'brand', 'categories'])->withItemCounts()
            ->when($request->filled('search'), fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($request->search).'%']))
            ->when($request->filled('type'), fn ($q) => $q->where('accessory_type_id', $request->type))
            ->orderBy('name')->paginate(12)->withQueryString()
            ->through(fn ($product) => $this->serialize($product, $request));
    }

    private function serialize(Product $product, Request $request): array
    {
        $data = (new NewProductResource($product))->resolve($request);
        $data['images'] = array_map(fn ($path) => \Illuminate\Support\Facades\Storage::disk('s3')->url($path), $product->images ?? []);

        return array_intersect_key($data, array_flip(['id', 'name', 'images', 'description', 'price', 'original_price', 'currency'])) + [
            'type' => $product->accessoryType?->name,
            'attributes' => $product->accessory_attributes ?? [],
            'available_stock' => $product->available_items,
        ];
    }

    public function index(Request $request)
    {
        return response()->json(['data' => $this->catalog($request), 'types' => AccessoryType::orderBy('name')->get(['id', 'name'])]);
    }

    public function show(Request $request, Product $accessory)
    {
        abort_unless($accessory->kind === 'accessory' && $accessory->is_active && $accessory->is_public, 404);
        $accessory->load(['accessoryType', 'brand', 'categories'])->loadCount(['items as available_items' => fn ($q) => $q->where('status', 'available')]);

        return response()->json(['data' => $this->serialize($accessory, $request)]);
    }
}
