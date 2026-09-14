<?php

namespace App\Services;

use App\Models\LowStockNotification;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class LowStockNotificationService
{
    public function sync(Product $product): ?LowStockNotification
    {
        return DB::transaction(function () use ($product) {
            $product = Product::query()
                ->whereKey($product->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $stockQuantity = $product->items()
                ->where('status', 'available')
                ->count();

            $activeNotification = $product->lowStockNotifications()
                ->whereNull('resolved_at')
                ->latest('id')
                ->first();

            $isLowStock = in_array($product->priority_level, [2, 3], true)
                && $stockQuantity < 2;

            if ($isLowStock) {
                if ($activeNotification) {
                    $activeNotification->update([
                        'stock_quantity' => $stockQuantity,
                        'priority_level' => $product->priority_level,
                    ]);

                    return $activeNotification;
                }

                return $product->lowStockNotifications()->create([
                    'stock_quantity' => $stockQuantity,
                    'priority_level' => $product->priority_level,
                    'status' => 'pending',
                ]);
            }

            if ($activeNotification) {
                $activeNotification->update([
                    'stock_quantity' => $stockQuantity,
                    'priority_level' => $product->priority_level,
                    'status' => 'completed',
                    'resolved_at' => now(),
                ]);
            }

            return $activeNotification;
        });
    }
}
