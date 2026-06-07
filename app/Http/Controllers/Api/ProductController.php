<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\NewProductResource;
use App\Models\Product;
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


    private function applyFilters($query, Request $request): void
    {
        $query->when($request->categoryId, function ($q, $categoryId) {
            $q->whereHas('categories', fn ($sub) =>
                $sub->where('categories.id', $categoryId)
            );
        });

        $query->when($request->search, function ($q, $search) {
            $q->where('name', 'ilike', "%{$search}%");
        });

        $query->when($request->brandId, fn ($q, $brandId) =>
            $q->where('brand_id', $brandId)
        );

        $query->when($request->collectionId, fn ($q, $collectionId) =>
            $q->where('collection_id', $collectionId)
        );

        $query->when($request->minPrice, fn ($q, $minPrice) =>
            $q->where('price', '>=', $minPrice)
        );

        $query->when($request->maxPrice, fn ($q, $maxPrice) =>
            $q->where('price', '<=', $maxPrice)
        );

        $query->when($request->minDialSize, fn ($q, $minDialSize) =>
            $q->where('dial_size', '>=', $minDialSize)
        );

        $query->when($request->maxDialSize, fn ($q, $maxDialSize) =>
            $q->where('dial_size', '<=', $maxDialSize)
        );

        $query->when($request->caseShape, fn ($q, $caseShape) =>
            $q->where('case_shape', $caseShape)
        );
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
