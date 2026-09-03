<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\LowStockNotificationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications)
    {
    }

    public function index()
    {
        return Inertia::render('Orders/Index', [
            'orders' => Order::with(['customer', 'user', 'items.product', 'items.soldItems'])->latest()->paginate(10),
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Orders/Show', [
            'order' => $order->load(['customer', 'user', 'items.product', 'items.soldItems']),
        ]);
    }

    public function approve(Order $order)
    {
        if ($order->status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'Only pending orders can be approved.']);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            foreach ($order->items as $orderItem) {
                // Find available stock
                $availableItems = \App\Models\ProductItem::where('product_id', $orderItem->product_id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($orderItem->quantity)
                    ->get();

                if ($availableItems->count() < $orderItem->quantity) {
                    $productName = $orderItem->product ? $orderItem->product->name : ('ID: ' . $orderItem->product_id);
                    throw new \Exception("Not enough stock for \"{$productName}\". Requested: {$orderItem->quantity}, Available: {$availableItems->count()}.");
                }

                // Mark items as sold and associate with order_item
                $itemIds = $availableItems->pluck('id')->toArray();
                \App\Models\ProductItem::whereIn('id', $itemIds)->update([
                    'status' => 'sold',
                    'order_item_id' => $orderItem->id,
                ]);
            }

            foreach ($order->items->pluck('product_id')->unique() as $productId) {
                $this->lowStockNotifications->sync(Product::findOrFail($productId));
            }

            $order->update(['status' => 'completed']);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', 'Order has been approved successfully.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Approval failed: ' . $e->getMessage()]);
        }
    }
}
