<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
   public function index()
    {
        // only parent categories
        $categories = Category::whereNull('parent_id')
            ->with(['children' => function($query) {
                $query->orderBy('sort_order', 'asc');
            }])
            ->orderBy('sort_order', 'asc')
            ->get();

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get Nested Categories Success',
            'data' => [
                'data' => CategoryResource::collection($categories),
            ],
        ]);
    }


    public function fetchAll()
    {
        $categories = Category::orderBy('sort_order', 'asc')->get();
        $categories = CategoryResource::collection($categories)->response()->getData(true);

        return response()->json([
            'code' => 200,
            'status' => 'success',
            'message' => 'Get All Categories Success',
            'data' => $categories,
        ]);
    }

}
