<?php

namespace App\Services;

use App\Models\ProductItem;
use Illuminate\Support\Carbon;

class WatchWarrantyService
{
    public function check(ProductItem $unit, ?string $asOf = null): array
    {
        $unit->load(['product' => fn ($query) => $query->withTrashed(), 'orderItem.order.customer' => fn ($query) => $query->withTrashed()]);
        $product = $unit->product;
        $order = $unit->orderItem?->order;
        $customer = $order?->customer;
        $checkedOn = Carbon::parse($asOf ?? now()->toDateString())->startOfDay();
        $purchase = $order?->status === 'completed' ? $order->created_at->copy()->startOfDay() : null;
        $months = $product?->warranty_period;
        $expires = $purchase && $months > 0 ? $purchase->copy()->addMonthsNoOverflow((int) $months) : null;
        [$result, $label] = match (true) {
            ! $product || $product->kind !== 'watch' => ['unverified', 'Watch could not be verified'],
            ! $purchase => ['unverified', 'Proof of purchase needed'],
            $checkedOn->lt($purchase) => ['unverified', 'Service date is before the purchase date'],
            $unit->status !== 'sold' || $unit->trashed() => ['unverified', 'Stock status needs review'],
            $months === null => ['unverified', 'Warranty period not recorded'],
            $months <= 0 => ['no_warranty', 'No warranty period'],
            $checkedOn->gt($expires) => ['expired', 'Warranty period expired'],
            default => ['within_period', 'Within warranty period'],
        };

        return [
            'result' => $result, 'label' => $label, 'checked_on' => $checkedOn->toDateString(),
            'purchase_date' => $purchase?->toDateString(), 'expires_on' => $expires?->toDateString(),
            'warranty_months' => $months, 'warranty_type' => $product?->warranty_type,
            'product_name' => $product?->name, 'barcode' => $unit->system_unique_id,
            'serial_number' => $unit->serial_number, 'unit_status' => $unit->status,
            'order_id' => $order?->id, 'order_number' => $order?->order_number,
            'customer_id' => $customer?->id, 'customer_name' => $customer?->name,
            'customer_phone' => $customer?->phone,
        ];
    }
}
