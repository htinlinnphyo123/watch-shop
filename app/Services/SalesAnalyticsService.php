<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalyticsService
{
    public function report(array $filters): array
    {
        return DB::transaction(function () use ($filters) {
            // Keep cards and charts on the same snapshot while POS sales are being recorded.
            if (DB::getDriverName() === 'pgsql' && DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            return $this->buildReport($filters);
        });
    }

    private function buildReport(array $filters): array
    {
        $start = Carbon::parse($filters['from'])->startOfDay();
        $end = Carbon::parse($filters['to'])->addDay()->startOfDay();
        $days = (int) $start->diffInDays($end);
        $orders = DB::table('orders')->where('orders.created_at', '>=', $start)->where('orders.created_at', '<', $end);
        $completed = (clone $orders)->where('orders.status', 'completed');
        // Aggregate orders separately from their lines so multi-item orders are counted once.
        $totals = (clone $completed)->selectRaw('COUNT(*) AS completed_orders, COALESCE(SUM(total_amount), 0) AS total_sales')->first();
        $previousSales = DB::table('orders')->where('status', 'completed')
            ->where('created_at', '>=', $start->copy()->subDays($days))->where('created_at', '<', $start)->sum('total_amount');
        $lines = (clone $completed)->join('order_items', 'order_items.order_id', '=', 'orders.id');
        $units = (clone $lines)->sum('order_items.quantity');
        $summary = [
            'total_sales' => $totals->total_sales,
            'completed_orders' => (int) $totals->completed_orders,
            'items_sold' => (int) $units,
            'average_sale' => $totals->completed_orders ? round($totals->total_sales / $totals->completed_orders, 2) : 0,
            'previous_sales' => $previousSales,
            'change_percent' => (float) $previousSales > 0 ? round(($totals->total_sales - $previousSales) / $previousSales * 100, 1) : null,
            'previous_from' => $start->copy()->subDays($days)->toDateString(),
            'previous_to' => $start->copy()->subDay()->toDateString(),
        ];

        $daily = (clone $completed)->selectRaw('DATE(orders.created_at) AS date, COUNT(*) AS orders, SUM(total_amount) AS sales')
            ->groupByRaw('DATE(orders.created_at)')->get()->keyBy('date');
        $trend = [];
        for ($date = $start->copy(); $date->lt($end); $date->addDay()) {
            $key = $date->toDateString();
            $trend[] = ['date' => $key, 'sales' => $daily[$key]->sales ?? 0, 'orders' => (int) ($daily[$key]->orders ?? 0)];
        }

        // Read historical order quantities, not today's stock state. Keep archived products visible.
        $products = (clone $lines)->leftJoin('products', 'products.id', '=', 'order_items.product_id');
        $topProducts = (clone $products)->select('order_items.product_id', 'products.name', 'products.kind')
            ->selectRaw('SUM(order_items.quantity) AS units, COUNT(DISTINCT orders.id) AS orders')
            ->groupBy('order_items.product_id', 'products.name', 'products.kind')
            ->orderByDesc('units')->orderByDesc('orders')->orderBy('order_items.product_id')->limit(5)->get();
        $productTypes = (clone $products)->selectRaw("COALESCE(products.kind, 'unknown') AS type, SUM(order_items.quantity) AS units")
            ->groupByRaw("COALESCE(products.kind, 'unknown')")->orderByDesc('units')->orderBy('type')->get();
        $statusCounts = (clone $orders)->selectRaw('status, COUNT(*) AS count')->groupBy('status')->pluck('count', 'status');
        $statuses = collect(['completed' => 'Completed', 'pending' => 'Pending', 'processing' => 'Processing', 'cancelled' => 'Cancelled'])
            ->map(fn ($label, $status) => ['status' => $status, 'label' => $label, 'count' => (int) ($statusCounts[$status] ?? 0)])->values();
        $recent = (clone $completed)->leftJoin('customers', 'customers.id', '=', 'orders.customer_id')
            ->select('orders.id', 'orders.order_number', 'orders.created_at', 'orders.total_amount', 'customers.name as customer_name')
            ->orderByDesc('orders.created_at')->orderByDesc('orders.id')->limit(5)->get();

        return compact('summary', 'trend', 'topProducts', 'productTypes', 'statuses', 'recent');
    }
}
