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
     * User provides: quantity (required), optional serial numbers (one per line), purchase_date, status.
     * Each item gets an auto-generated system_unique_id (12-digit transaction code).
     * Serial number is optional — left null if not provided.
     */
    public function store(Request $request, Product $product)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1|max:500',
            'purchase_date' => 'nullable|date',
            'status' => 'required|in:available,sold,reserved,returned,lost,damaged',
        ]);

        $qty = (int) $request->quantity;

        for ($i = 0; $i < $qty; $i++) {
            $product->items()->create([
                'serial_number' => null,
                'system_unique_id' => $this->generateUniqueSystemId(),
                'purchase_date' => $request->purchase_date ?: null,
                'status' => $request->status,
            ]);
        }

        $this->lowStockNotifications->sync($product);

        return redirect()->back()->with('success', "{$qty} stock item(s) added successfully.");
    }

    /**
     * Update an individual item — serial number optional, system_unique_id preserved.
     */
    public function update(Request $request, ProductItem $item)
    {
        $validated = $request->validate([
            'serial_number' => 'nullable|string|unique:product_items,serial_number,'.$item->id,
            'status' => 'required|in:available,sold,reserved,returned,lost,damaged',
            'system_unique_id' => 'nullable|string|size:12|unique:product_items,system_unique_id,'.$item->id,
            'purchase_date' => 'nullable|date',
        ]);

        // Don't overwrite system_unique_id unless explicitly provided
        if (empty($validated['system_unique_id'])) {
            unset($validated['system_unique_id']);
        }

        DB::transaction(function () use ($item, $validated) {
            $locked = ProductItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($validated['status'] !== $locked->status) {
                $this->ensureNotReserved($locked);
            }
            $locked->update($validated);
        });
        $this->lowStockNotifications->sync($item->product);

        return redirect()->back();
    }

    public function destroy(ProductItem $item)
    {
        $product = $item->product;
        DB::transaction(function () use ($item) {
            $locked = ProductItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $this->ensureNotReserved($locked);
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

    private function generateUniqueSystemId(): string
    {
        do {
            $id = '';
            for ($i = 0; $i < 12; $i++) {
                $id .= mt_rand(0, 9);
            }
        } while (ProductItem::where('system_unique_id', $id)->exists());

        return $id;
    }
}
