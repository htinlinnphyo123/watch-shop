<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\LowStockNotificationService;
use App\Services\OrderSummaryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications) {}

    public function index(Request $request, OrderSummaryService $summaryService)
    {
        $filters = $this->filters($request);
        $query = $summaryService->filter(Order::query(), $filters);
        $orders = $query->with(['customer', 'user'])->latest()->orderByDesc('id')->paginate(10)->withQueryString();
        $orders->through(function (Order $order) use ($summaryService) {
            $payments = $summaryService->retainedPayments($order);

            return [...$order->toArray(), 'payment_breakdown' => $payments === null
                ? null : array_map(fn ($amount) => $amount / 100, $payments)];
        });

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
        ]);
    }

    public function summary(Request $request, OrderSummaryService $summaryService)
    {
        $filters = $this->filters($request);
        $query = $summaryService->filter(Order::query(), $filters);

        return Inertia::render('Orders/Summary', [
            'filters' => $filters,
            'summary' => $summaryService->summarize($query),
        ]);
    }

    private function filters(Request $request): array
    {
        return $request->validate([
            'payment_method' => 'nullable|in:cash,kbz_pay,card,transfer,cb_pay,aya_pay,other',
            'payment_type' => 'nullable|in:single,split',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Orders/Show', [
            'order' => $order->load(['customer', 'user', 'items.product', 'items.soldItems', 'fileUploads']),
        ]);
    }

    public function approve(Order $order)
    {
        if ($order->status !== 'pending') {
            return redirect()->back()->withErrors(['error' => 'Only pending orders can be approved.']);
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'pending') {
                throw new \Exception('This order is no longer pending. Refresh the order before continuing.');
            }

            $auditBefore = app(\App\Services\OrderAuditService::class)->before($order);
            foreach ($order->items as $orderItem) {
                // Find available stock
                $availableItems = \App\Models\ProductItem::where('product_id', $orderItem->product_id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->limit($orderItem->quantity)
                    ->get();

                if ($availableItems->count() < $orderItem->quantity) {
                    $productName = $orderItem->product ? $orderItem->product->name : ('ID: '.$orderItem->product_id);
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

            $order->update(['status' => 'completed', 'edit_version' => $order->edit_version + 1]);
            app(\App\Services\OrderAuditService::class)->record($order, 'approved', $auditBefore);

            \Illuminate\Support\Facades\DB::commit();

            return redirect()->back()->with('success', 'Order has been approved successfully.');

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();

            return redirect()->back()->withErrors(['error' => 'Approval failed: '.$e->getMessage()]);
        }
    }
}
