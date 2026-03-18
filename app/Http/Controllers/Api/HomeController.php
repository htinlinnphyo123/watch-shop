<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\BannerResource;
use App\Http\Resources\Api\BrandResource;
use App\Http\Resources\Api\ProductResource;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $banners = Banner::all();
        $brands = Brand::all();
        $banners = BannerResource::collection($banners);
        $brands = BrandResource::collection($brands);
        $featureProducts = Product::where('is_active', true)
            ->where("is_public", true)
            ->where('is_featured', true)
            ->limit(4)
            ->get();
        $featureProducts = ProductResource::collection($featureProducts);
        $adminChoices = Product::where('is_active', true)
            ->where("is_public", true)
            ->limit(10)
            ->orderBy('created_at', 'desc')
            ->get(); 
        $adminChoices = ProductResource::collection($adminChoices);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Home Data Success',
            'data' => [
                'banners' => $banners,
                'feature_products' => $featureProducts,
                'admin_choices' => $adminChoices,
                'brands' => $brands,
            ],
        ]);
    }
}
