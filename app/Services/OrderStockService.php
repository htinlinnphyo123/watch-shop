<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderStockService
{
    public function hasAllocatedStock(Order $order): bool
    {
        return $order->status === 'completed' || $order->stock_reserved_at !== null;
    }

    public function expectedStatus(Order $order): string
    {
        return $order->status === 'completed' ? 'sold' : 'reserved';
    }

    /** Mutation callers must lock the order and its linked units first. */
    public function assertIntact(Order $order, Collection $lines): void
    {
        foreach ($lines as $line) {
            if (! $this->hasAllocatedStock($order)) {
                if ($line->soldItems->isNotEmpty()) {
                    throw ValidationException::withMessages(['error' => 'This order has unexpected stock links. Review its inventory before continuing.']);
                }
                continue;
            }
            if ($line->soldItems->count() !== $line->quantity || $line->soldItems->contains(
                fn ($unit) => $unit->status !== $this->expectedStatus($order) || $unit->product_id !== $line->product_id
            )) {
                throw ValidationException::withMessages(['error' => 'The linked stock records have changed or are incomplete. Review this order before continuing.']);
            }
        }
    }

    public function release(Order $order, Collection $lines): void
    {
        $this->assertIntact($order, $lines);
        ProductItem::whereIn('order_item_id', $lines->pluck('id'))
            ->where('status', $this->expectedStatus($order))
            ->update(['status' => 'available', 'order_item_id' => null]);
    }
}
