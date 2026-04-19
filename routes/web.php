<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('categories/reorder', [\App\Http\Controllers\CategoryController::class, 'reorder'])->name('categories.reorder');
    Route::resource('categories', \App\Http\Controllers\CategoryController::class);
    Route::post('brands/reorder', [\App\Http\Controllers\BrandController::class, 'reorder'])->name('brands.reorder');
    Route::resource('brands', \App\Http\Controllers\BrandController::class);
    Route::post('collections/reorder', [\App\Http\Controllers\CollectionController::class, 'reorder'])->name('collections.reorder');
    Route::resource('collections', \App\Http\Controllers\CollectionController::class);
    Route::get('products/export', [\App\Http\Controllers\ProductController::class, 'export'])->name('products.export');
    Route::post('products/presigned-url', [\App\Http\Controllers\ProductController::class, 'presignedUrl'])->name('products.presigned-url');
    Route::post('products/import', [\App\Http\Controllers\ProductController::class, 'import'])->name('products.import');
    Route::resource('products', \App\Http\Controllers\ProductController::class);
    Route::post('/products/{product}/items', [\App\Http\Controllers\ProductItemController::class, 'store'])->name('products.items.store');
    Route::put('/items/{item}', [\App\Http\Controllers\ProductItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [\App\Http\Controllers\ProductItemController::class, 'destroy'])->name('items.destroy');
    
    // Admin Only Routes
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('customer-groups', \App\Http\Controllers\CustomerGroupController::class);
        Route::resource('banners', \App\Http\Controllers\BannerController::class);
        Route::resource('top-level-discounts', \App\Http\Controllers\TopLevelDiscountController::class);
        Route::get('settings', [\App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
    });

    // Shared Routes (Staff & Admin)
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);
    
    Route::get('/pos', [\App\Http\Controllers\POSController::class, 'index'])->name('pos.index');
    Route::post('/pos/checkout', [\App\Http\Controllers\POSController::class, 'checkout'])->name('pos.checkout');

    Route::resource('orders', \App\Http\Controllers\OrderController::class)->only(['index', 'show']);
});

require __DIR__.'/auth.php';
