<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Services\LowStockNotificationService;
use App\Services\OrderSummaryService;
use App\Services\OrderStockService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications) {}

    public function index(Request $request, OrderSummaryService $summaryService)
    {
        $filters = $this->filters($request);
        $query = $summaryService->filter(Order::query(), $filters);
        if ($request->user()->role === 'staff') {
            $query->where('user_id', $request->user()->id);
        }
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

    public function export(Request $request, OrderSummaryService $summaryService, \App\Services\SalesWorkbookExport $export)
    {
        $query = $summaryService->filter(Order::query(), $this->filters($request))->where('status', 'completed');
        if ($request->user()->role === 'staff') $query->where('user_id', $request->user()->id);
        $orders = $query->with(['customer' => fn ($q) => $q->withTrashed(), 'user', 'items.product' => fn ($q) => $q->withTrashed(), 'items.product.brand' => fn ($q) => $q->withTrashed()])->lazyById(200);
        return $export->download($orders->map(fn (Order $order) => $export->saleRow($order)));
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
            $lines = $order->items()->with(['soldItems' => fn ($query) => $query->lockForUpdate()])->get();
            $stock = app(OrderStockService::class);
            $stock->assertIntact($order, $lines);
            foreach ($lines as $orderItem) {
                // New POS orders already own units. Legacy/online orders allocate on approval.
                $availableItems = $stock->hasAllocatedStock($order)
                    ? $orderItem->soldItems
                    : \App\Models\ProductItem::where('product_id', $orderItem->product_id)
                        ->where('status', 'available')
                        ->orderBy('created_at')->orderBy('id')
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

    public function cancel(Order $order)
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($order) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'cancelled') {
                return;
            }
            abort_unless(in_array($order->status, ['pending', 'completed'], true), 422);
            $audit = app(\App\Services\OrderAuditService::class);
            $before = $audit->before($order);
            $lines = $order->items()->with(['soldItems' => fn ($query) => $query->lockForUpdate()])->get();
            app(OrderStockService::class)->release($order, $lines);
            $order->update(['status' => 'cancelled', 'edit_version' => $order->edit_version + 1]);
            foreach ($lines->pluck('product_id')->unique()->sort() as $productId) {
                if ($product = Product::find($productId)) {
                    $this->lowStockNotifications->sync($product);
                }
            }
            $audit->record($order, 'cancelled', $before);
        });

        return redirect()->back()->with('success', 'Order cancelled. Its reserved or sold stock has been returned to available inventory.');
    }
}
