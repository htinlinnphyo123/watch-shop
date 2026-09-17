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
    Route::patch('categories/{category}/toggle-show', [\App\Http\Controllers\CategoryController::class, 'toggleShow'])->name('categories.toggle-show');
    Route::resource('categories', \App\Http\Controllers\CategoryController::class);
    Route::post('brands/reorder', [\App\Http\Controllers\BrandController::class, 'reorder'])->name('brands.reorder');
    Route::resource('brands', \App\Http\Controllers\BrandController::class);
    Route::post('collections/reorder', [\App\Http\Controllers\CollectionController::class, 'reorder'])->name('collections.reorder');
    Route::resource('collections', \App\Http\Controllers\CollectionController::class);
    Route::get('products/export', [\App\Http\Controllers\ProductController::class, 'export'])->middleware('role:admin')->name('products.export');
    Route::post('products/presigned-url', [\App\Http\Controllers\ProductController::class, 'presignedUrl'])->name('products.presigned-url');
    Route::post('products/import', [\App\Http\Controllers\ProductController::class, 'import'])->name('products.import');
    Route::resource('products', \App\Http\Controllers\ProductController::class);
    Route::post('/products/{product}/items', [\App\Http\Controllers\ProductItemController::class, 'store'])->name('products.items.store');
    Route::put('/items/{item}', [\App\Http\Controllers\ProductItemController::class, 'update'])->name('items.update');
    Route::delete('/items/{item}', [\App\Http\Controllers\ProductItemController::class, 'destroy'])->name('items.destroy');
    Route::get('wallet', [\App\Http\Controllers\WalletController::class, 'index'])->name('wallet.index');
    Route::post('wallet/transactions', [\App\Http\Controllers\WalletController::class, 'storeTransaction'])->name('wallet.transactions.store');
    Route::put('wallet/transactions/{walletTransaction}', [\App\Http\Controllers\WalletController::class, 'updateTransaction'])->name('wallet.transactions.update');
    Route::delete('wallet/transactions/{walletTransaction}', [\App\Http\Controllers\WalletController::class, 'destroyTransaction'])->name('wallet.transactions.destroy');

    // Inventory management is available to every authenticated user.
    Route::resource('banners', \App\Http\Controllers\BannerController::class);

    Route::resource('pre-orders', \App\Http\Controllers\PreOrderController::class)->only(['index', 'store', 'update']);
    Route::post('pre-orders/{preOrder}/files/presign', [\App\Http\Controllers\PreOrderAttachmentController::class, 'presign'])->middleware('throttle:60,1')->name('pre-orders.files.presign');
    Route::post('pre-orders/{preOrder}/files/{attachment}/complete', [\App\Http\Controllers\PreOrderAttachmentController::class, 'complete'])->name('pre-orders.files.complete');
    Route::get('pre-orders/{preOrder}/files/{attachment}', [\App\Http\Controllers\PreOrderAttachmentController::class, 'download'])->name('pre-orders.files.download');
    // Admin Only Routes
    Route::middleware(['role:admin'])->group(function () {
        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::resource('customer-groups', \App\Http\Controllers\CustomerGroupController::class);
        Route::resource('top-level-discounts', \App\Http\Controllers\TopLevelDiscountController::class);
        Route::get('settings', [\App\Http\Controllers\SettingController::class, 'index'])->name('settings.index');
        Route::put('settings', [\App\Http\Controllers\SettingController::class, 'update'])->name('settings.update');
        Route::get('low-stock-notifications', [\App\Http\Controllers\LowStockNotificationController::class, 'index'])->name('low-stock-notifications.index');
        Route::patch('low-stock-notifications/{lowStockNotification}', [\App\Http\Controllers\LowStockNotificationController::class, 'update'])->name('low-stock-notifications.update');
    });

    // Shared Routes (Staff & Admin)
    Route::resource('customers', \App\Http\Controllers\CustomerController::class);

    Route::get('/pos', [\App\Http\Controllers\POSController::class, 'index'])->name('pos.index');
    Route::get('/pos/products', [\App\Http\Controllers\POSController::class, 'products'])->name('pos.products');
    Route::get('/pos/products/scan', [\App\Http\Controllers\POSController::class, 'scan'])->name('pos.products.scan');
    Route::get('/pos/products/{product}/available-items', [\App\Http\Controllers\POSController::class, 'availableItems'])->name('pos.products.available-items');
    Route::post('/pos/checkout', [\App\Http\Controllers\POSController::class, 'checkout'])->name('pos.checkout');
    Route::put('/pos/orders/{order}', [\App\Http\Controllers\POSController::class, 'update'])->name('pos.orders.update');

    Route::get('orders/summary', [\App\Http\Controllers\OrderController::class, 'summary'])->name('orders.summary');
    Route::get('orders/{order}/history', \App\Http\Controllers\OrderHistoryController::class)->name('orders.history');
    Route::resource('orders', \App\Http\Controllers\OrderController::class)->only(['index', 'show']);
    Route::post('orders/{order}/files/presign', [\App\Http\Controllers\OrderAttachmentController::class, 'presign'])->middleware('throttle:60,1')->name('orders.files.presign');
    Route::post('orders/{order}/files/{attachment}/complete', [\App\Http\Controllers\OrderAttachmentController::class, 'complete'])->name('orders.files.complete');
    Route::get('orders/{order}/files/{attachment}', [\App\Http\Controllers\OrderAttachmentController::class, 'download'])->name('orders.files.download');
    Route::post('orders/{order}/approve', [\App\Http\Controllers\OrderController::class, 'approve'])->name('orders.approve');
});

require __DIR__.'/auth.php';

// Accessory administration; public consumers use the API routes.
Route::middleware(['auth', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('accessories', [\App\Http\Controllers\AccessoryController::class, 'index'])->name('accessories.index');
    Route::post('accessories', [\App\Http\Controllers\AccessoryController::class, 'store'])->name('accessories.store');
    Route::put('accessories/{accessory}', [\App\Http\Controllers\AccessoryController::class, 'update'])->name('accessories.update');
    Route::post('accessories/{accessory}/labels', [\App\Http\Controllers\AccessoryController::class, 'labels'])->name('accessories.labels');
    Route::post('accessories/{accessory}/stock', [\App\Http\Controllers\AccessoryController::class, 'stock'])->name('accessories.stock');
    Route::post('accessory-types', [\App\Http\Controllers\AccessoryController::class, 'saveType'])->name('accessory-types.store');
    Route::put('accessory-types/{type}', [\App\Http\Controllers\AccessoryController::class, 'saveType'])->name('accessory-types.update');
});
