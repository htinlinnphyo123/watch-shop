<?php

namespace App\Http\Controllers;

use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\LowStockNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductItemController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications) {}

    /**
     * Bulk-add stock items.
     * User provides quantity and an optional purchase date. New units are available.
     * Each item gets an auto-generated system_unique_id (12-digit transaction code).
     * Serial number is optional — left null if not provided.
     */
    public function store(Request $request, Product $product)
    {
        abort_if($product->kind === 'accessory' && $request->user()->role !== 'admin', 403);
        $request->validate([
            'quantity' => 'required|integer|min:1|max:500',
            'purchase_date' => 'nullable|date',
            'status' => 'required|in:available',
        ]);

        $qty = (int) $request->quantity;

        DB::transaction(function () use ($qty, $product, $request) {
            for ($i = 0; $i < $qty; $i++) {
                $product->items()->create([
                    'serial_number' => null,
                    'system_unique_id' => app(\App\Services\StockCodeService::class)->generate(),
                    'purchase_date' => $request->purchase_date ?: null,
                    'status' => 'available',
                ]);
            }
            $this->lowStockNotifications->sync($product);
        });

        return redirect()->back()->with('success', "{$qty} stock item(s) added successfully.");
    }

    /**
     * Update an individual item — serial number optional, system_unique_id preserved.
     */
    public function update(Request $request, ProductItem $item)
    {
        abort_if($item->product?->kind === 'accessory' && auth()->user()->role !== 'admin', 403);
        $validated = $request->validate([
            'serial_number' => 'nullable|string|max:255|unique:product_items,serial_number,'.$item->id,
            'status' => 'required|in:available,sold,reserved,returned,lost,damaged',
            'system_unique_id' => ['nullable', 'string', 'regex:/^[0-9]{12}$/', 'unique:product_items,system_unique_id,'.$item->id],
            'purchase_date' => 'nullable|date',
        ]);

        // Don't overwrite system_unique_id unless explicitly provided
        if (empty($validated['system_unique_id'])) {
            unset($validated['system_unique_id']);
        }

        DB::transaction(function () use ($item, $validated) {
            $locked = ProductItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($locked->system_unique_id && isset($validated['system_unique_id']) && $validated['system_unique_id'] !== $locked->system_unique_id) {
                throw ValidationException::withMessages(['system_unique_id' => 'The system barcode cannot be changed after it is assigned.']);
            }
            if ($validated['status'] !== $locked->status) {
                $this->ensureNotReserved($locked);
                if ($locked->order_item_id || $locked->status === 'sold' || in_array($validated['status'], ['sold', 'reserved'], true)) {
                    throw ValidationException::withMessages(['status' => 'This unit is linked to an order or reservation. Manage its stock through that record.']);
                }
            }
            $locked->update($validated);
        });
        $this->lowStockNotifications->sync($item->product);

        return redirect()->back();
    }

    public function destroy(ProductItem $item)
    {
        abort_if($item->product?->kind === 'accessory' && auth()->user()->role !== 'admin', 403);
        $product = $item->product;
        DB::transaction(function () use ($item) {
            $locked = ProductItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->ensureNotReserved($locked);
            if ($locked->status !== 'available' || $locked->order_item_id) {
                throw ValidationException::withMessages(['status' => 'Only available units without a sale link can be removed.']);
            }
            $locked->delete();
        });
        $this->lowStockNotifications->sync($product);

        return redirect()->back();
    }

    private function ensureNotReserved(ProductItem $item): void
    {
        if (PreOrder::where('product_item_id', $item->id)->where('type', 'reservation')->whereIn('status', ['pending', 'completed'])->exists()) {
            throw ValidationException::withMessages(['status' => 'This watch belongs to a reservation. Manage it from Pre Orders & Reservations.']);
        }
    }

}
