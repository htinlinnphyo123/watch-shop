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

    if ($request->has('category_id')) {
        $query->whereHas('categories', function ($q) use ($request) {
            $q->where('categories.id', $request->category_id);
        });
    }

    if ($request->has('brand_id')) {
        $query->where('brand_id', $request->brand_id);
    }

    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%");
        });
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
        return new ProductResource($product->load(['brand', 'category']));
    }
}
