<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BannerResource;
use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\CategoryResource;
use App\Http\Resources\Api\CollectionResource;
use App\Http\Resources\Api\NewProductResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Product;

class HomeController extends Controller
{
   public function index()
    {
        // Base product query (reuse)
        $baseProductQuery = Product::where('is_active', true)
            ->where('is_public', true);

        $banners = BannerResource::collection(Banner::orderBy('order', 'asc')->where("is_active", true)->get())->resolve();
        $brands = BrandResource::collection(Brand::orderBy('sort_order', 'asc')->orderBy('created_at', 'asc')->get())->resolve();
        $collections = CollectionResource::collection(Collection::orderBy('sort_order', 'asc')->orderBy('created_at', 'asc')->get())->resolve();

        $featureProducts = ProductResource::collection(
            (clone $baseProductQuery)
                ->where('is_featured', true)
                ->latest()
                ->limit(4)
                ->get()
        )->resolve();

        $adminChoices = NewProductResource::collection(
            (clone $baseProductQuery)
                ->latest()
                ->limit(10)
                ->get()
        )->resolve();

        $isLatest = NewProductResource::collection(
            (clone $baseProductQuery)
                ->where('is_latest', true)
                ->latest()
                ->limit(10)
                ->get()
        )->resolve();

        $isLimited = NewProductResource::collection(
            (clone $baseProductQuery)
                ->where('is_limited_collection', true)
                ->latest()
                ->limit(10)
                ->get()
        )->resolve();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Home Data Success',
            'data' => [
                'banners' => $banners,
                'feature_products' => $featureProducts,
                'admin_choices' => $adminChoices,
                'is_latest' => $isLatest,
                'brands' => $brands,
                'collections' => $collections,
                'is_limited' => $isLimited,
            ],
        ]);
    }

    public function filterNecessaryData()
    {
         // Run queries in parallel style (cleaner)
        $categories = CategoryResource::collection(Category::orderBy('sort_order', 'asc')->get())->resolve();
        $brands = BrandResource::collection(Brand::orderBy('sort_order', 'asc')->get())->resolve();
        $collections = CollectionResource::collection(Collection::orderBy('sort_order', 'asc')->get())->resolve();

        // Single query for product attributes
        $productAttributes = Product::where('is_active', true)
            ->where('is_public', true)
            ->select('case_shape', 'dial_size')
            ->distinct()
            ->get();

        $caseShapes = $productAttributes
            ->pluck('case_shape')
            ->filter()
            ->unique()
            ->values()
            ->map(fn($item) => [
                'id' => $item,
                'name' => $item,
            ]);

        $dialSizes = $productAttributes
            ->pluck('dial_size')
            ->filter()
            ->unique()
            ->values()
            ->map(fn($item) => [
                'id' => $item,
                'name' => $item,
            ]);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Filter Necessary Data Success',
            'data' => [
                'categories' => $categories,
                'brands' => $brands,
                'collections' => $collections,
                'case_shapes' => $caseShapes,
                'dial_sizes' => $dialSizes,
            ],
        ]);
    }
}
