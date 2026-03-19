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
    $query = Product::with(['brand', 'categories']);

    if ($request->has('categoryId')) {
        $query->whereHas('categories', function ($q) use ($request) {
            $q->where('categories.id', $request->categoryId);
        });
    }

    if ($request->has('brandId')) {
        $query->where('brand_id', $request->brandId);
    }

    if($request->has('collectionId')) {
        $query->where('collection_id', $request->collectionId);
    }

    if($request->has('minPrice')) {
        $query->where('price', '>=', $request->minPrice);
    }

    if($request->has('maxPrice')) {
        $query->where('price', '<=', $request->maxPrice);
    }

    if($request->has('dialSize')) {
        $query->where('dial_size', $request->dialSize);
    }

    if($request->has('caseShape')) {
        $query->where('case_shape', $request->caseShape);
    }

    

    $products = $query->paginate($request->limit ?? 12);
    $products = ProductResource::collection($products)->response()->getData(true);


    return response()->json([
        'code' => 200,
        'status' => 'success',
        'message' => 'Get Products Success',
        'data' => $products,
    ]);
}

    public function show(Product $product)
    {
        $product = new ProductResource($product->load(['brand', 'categories']));
        $relatedProducts = Product::where('id', '!=', $product->id)->get();
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
