<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NewProductResource;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseQuery();

        $this->applyFilters($query, $request);
        $this->applySorting($query, $request);
        $products = $this->paginate($query, $request);
        $products = NewProductResource::collection($products)->response()->getData(true);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Products Success',
            'data' => $products,
        ]);
    }

    private function baseQuery()
    {
        return Product::query()
            ->with(['brand', 'categories',])
            ->withItemCounts()
            ->where('is_active', true)
            ->where('is_public', true);
    }


    /**
     * Extract an array parameter from the request, supporting:
     *   ?key=1,2,3      (comma-separated string)
     *   ?key[]=1&key[]=2 (standard array notation)
     *   ?key=1&key=2     (duplicate keys - PHP keeps only the last one)
     */
    private function getArrayParam(Request $request, string $key): ?array
    {
        $value = $request->query($key);

        if (empty($value)) {
            return null;
        }

        // Already an array (e.g. ?key[]=1&key[]=2)
        if (is_array($value)) {
            return $value;
        }

        // Comma-separated string (e.g. ?key=1,2,3)
        if (is_string($value) && str_contains($value, ',')) {
            return explode(',', $value);
        }
        
        // Single value
        return [$value];
    }

    private function applyFilters($query, Request $request): void
    {
        $categoryIds = $this->getArrayParam($request, 'categoryId');
        if ($categoryIds) {
            $query->whereHas('categories', fn ($sub) =>
                $sub->whereIn('categories.id', $categoryIds)
            );
        }

        $query->when($request->search, function ($q, $search) {
            $q->where('name', 'ilike', "%{$search}%")
                ->orWhere('model_number', 'ilike', "%{$search}%");
        });

        $brandIds = $this->getArrayParam($request, 'brandId');
        if ($brandIds) {
            $query->whereIn('brand_id', $brandIds);
        }

        $collectionIds = $this->getArrayParam($request, 'collectionId');
        if ($collectionIds) {
            $query->whereIn('collection_id', $collectionIds);
        }

        $rates = Setting::whereIn('key', ['usd_rate', 'thb_rate', 'sgd_rate', 'cny_rate'])
            ->pluck('value', 'key');
        $usdRate = (float) ($rates['usd_rate'] ?? 1);
        $thbRate = (float) ($rates['thb_rate'] ?? 1);
        $sgdRate = (float) ($rates['sgd_rate'] ?? 1);
        $cnyRate = (float) ($rates['cny_rate'] ?? 1);

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

        if ($request->filled('minPrice')) {
            $query->whereRaw("({$mmkPriceExpression}) >= ?", [$request->minPrice]);
        }

        if ($request->filled('maxPrice')) {
            $query->whereRaw("({$mmkPriceExpression}) <= ?", [$request->maxPrice]);
        }

        $query->when($request->minDialSize, fn ($q, $minDialSize) =>
            $q->where('dial_size', '>=', $minDialSize)
        );

        $query->when($request->maxDialSize, fn ($q, $maxDialSize) =>
            $q->where('dial_size', '<=', $maxDialSize)
        );

        $caseShapes = $this->getArrayParam($request, 'caseShape');
        if ($caseShapes) {
            $query->whereIn('case_shape', $caseShapes);
        }

        $strapMaterials = $this->getArrayParam($request, 'strapMaterial');
        if ($strapMaterials) {
            $query->whereIn('strap_material', $strapMaterials);
        }

        $movements = $this->getArrayParam($request, 'movement');
        if ($movements) {
            $query->whereIn('movement', $movements);
        }
    }

    private function applySorting($query, Request $request): void
    {
        $query->when($request->sortBy, function ($q, $sortBy) use ($request) {
            $direction = $request->input('sortDirection', 'asc');
            $q->orderBy($sortBy, $direction);
        }, function ($q) {
            $q->latest();
        });
    }

    private function paginate($query, Request $request)
    {
        $perPage = $request->input('per_page', $request->input('limit', 12));
        return $query->paginate($perPage);
    }

    public function show(Product $product)
    {
        $product->load(['brand', 'categories'])->loadCount([
            'items as total_items',
            'items as available_items' => function ($q) {
                $q->where('status', 'available');
            },
            'items as sold_items' => function ($q) {
                $q->where('status', 'sold');
            },
            'items as reserved_items' => function ($q) {
                $q->where('status', 'reserved');
            }
        ]);

        $productResource = new NewProductResource($product);
        $relatedProducts = $this->baseQuery()
            ->where('id', '!=', $product->id)
            ->inRandomOrder()
            ->limit(4)
            ->get();
        $relatedProducts = NewProductResource::collection($relatedProducts)->response()->getData(true);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Product Success',
            'data' => [
                'product'=>$productResource,
                'related_products'=>$relatedProducts['data']    
            ]
        ]);
    }
}
