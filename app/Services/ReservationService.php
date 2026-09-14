<?php

namespace App\Services;

use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService
{
    public function save(array $data, int $creatorId, ?PreOrder $existing = null): PreOrder
    {
        return DB::transaction(function () use ($data, $creatorId, $existing) {
            $record = $existing ? PreOrder::whereKey($existing->id)->lockForUpdate()->firstOrFail() : new PreOrder(['user_id' => $creatorId]);
            $data['type'] ??= $record->type ?? 'pre_order';
            $data['product_item_id'] = $data['type'] === 'reservation' ? ($data['product_item_id'] ?? null) : null;
            $terminal = $record->exists && in_array($record->status, ['completed', 'cancelled'], true);
            if ($terminal && ($data['status'] !== $record->status || $data['type'] !== $record->type
                || (int) $data['product_item_id'] !== (int) $record->product_item_id
                || (int) ($data['product_id'] ?? null) !== (int) $record->product_id
                || (int) $data['customer_id'] !== (int) $record->customer_id)) {
                throw ValidationException::withMessages(['status' => 'Completed or cancelled records cannot be reopened or reassigned. Create a new reservation.']);
            }
            if (! $terminal && $data['status'] === 'completed' && (! $record->exists || $record->type !== 'reservation'
                || $record->status !== 'pending' || (int) $data['product_item_id'] !== (int) $record->product_item_id)) {
                throw ValidationException::withMessages(['status' => 'Save an active reservation before completing it.']);
            }
            $ids = array_filter([$record->product_item_id, $data['product_item_id']]);
            $items = ProductItem::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $old = $items->get($record->product_item_id);
            $oldActive = $record->type === 'reservation' && $record->status === 'pending';
            $newActive = $data['type'] === 'reservation' && $data['status'] === 'pending';
            $new = $items->get($data['product_item_id']);
            if (! $terminal && $data['type'] === 'reservation') {
                if (! $new || $new->product_id !== (int) $data['product_id']) {
                    throw ValidationException::withMessages(['product_item_id' => 'Choose a watch belonging to the selected product.']);
                }
                $ownHold = $oldActive && $record->product_item_id === $new->id;
                if (($ownHold && $new->status !== 'reserved') || (! $ownHold && $new->status !== 'available')) {
                    throw ValidationException::withMessages(['product_item_id' => 'This watch is no longer available. Refresh and choose another watch.']);
                }
            }
            if ($oldActive && (! $old || $old->status !== 'reserved')) {
                throw ValidationException::withMessages(['product_item_id' => 'The reserved watch has changed. Refresh and review the reservation.']);
            }
            if ($oldActive && (! $newActive || $old->id !== $new?->id)) {
                $old->update(['status' => $data['status'] === 'completed' ? 'sold' : 'available']);
            }
            if ($newActive) {
                $new->update(['status' => 'reserved']);
            }
            $record->fill($data)->save();
            foreach (Product::whereIn('id', $items->pluck('product_id')->unique())->get() as $product) {
                app(LowStockNotificationService::class)->sync($product);
            }

            return $record;
        }, 3);
    }
}
