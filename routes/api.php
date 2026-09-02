<?php

use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public Routes

Route::prefix('v1/spa')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::get('home', [HomeController::class, 'index']);
    Route::get('products', [ProductController::class, 'index']);
    Route::get('products/{product}', [ProductController::class, 'show']);
    Route::get('categories', [CategoryController::class, 'index']);
    Route::get('categories/fetch-all', [CategoryController::class, 'fetchAll']);
    Route::get('filter-necessary-data', [HomeController::class, 'filterNecessaryData']);
    Route::get('articles', [ArticleController::class, 'index']);
    Route::get('articles/{slug}', [ArticleController::class, 'show']);
});

// Protected Routes
Route::middleware('auth:sanctum')->prefix('v1/spa')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('orders/preview', [\App\Http\Controllers\Api\FrontendOrderController::class, 'preview']);
    Route::post('orders', [\App\Http\Controllers\Api\FrontendOrderController::class, 'store']);
    Route::get('user', function (Request $request) {
        $user = $request->user();
        if ($user instanceof \App\Models\Customer) {
            return $user->load('group');
        }
        return $user->load('customer.group');
    });
});
