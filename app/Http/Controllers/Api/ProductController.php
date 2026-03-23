<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\ProductResource;
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
        $products = ProductResource::collection($products)->response()->getData(true);

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
            ->with(['brand', 'categories'])
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

        $query->when($request->dialSize, fn ($q, $dialSize) =>
            $q->where('dial_size', $dialSize)
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
        return $query->paginate($request->input('limit', 12));
    }

    public function show(Product $product)
    {
        $product = new ProductResource($product->load(['brand', 'categories']));
        $relatedProducts = Product::where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->inRandomOrder()
            ->limit(4)
            ->get();
        $relatedProducts = ProductResource::collection($relatedProducts)->response()->getData(true);
        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Product Success',
            'data' => [
                'product'=>$product,
                'related_products'=>$relatedProducts['data']    
            ]
        ]);
    }
}
