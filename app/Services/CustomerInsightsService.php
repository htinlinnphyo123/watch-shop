<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CustomerInsightsService
{
    public function report(array $filters): array
    {
        $orders = DB::table('orders')->where('orders.status', 'completed')
            ->where('orders.created_at', '>=', Carbon::parse($filters['from'])->startOfDay())
            ->where('orders.created_at', '<', Carbon::parse($filters['to'])->addDay()->startOfDay());

        $summary = (clone $orders)->selectRaw('COUNT(*) AS completed_orders, COALESCE(SUM(total_amount), 0) AS purchase_value, COUNT(DISTINCT customer_id) AS purchasing_customers')->first();
        $joined = (clone $orders)->leftJoin('customers', 'customers.id', '=', 'orders.customer_id');
        $sourceExpression = "CASE WHEN customers.id IS NULL THEN 'guest' ELSE COALESCE(NULLIF(customers.source, ''), 'unknown') END";
        $sources = (clone $joined)->selectRaw("{$sourceExpression} AS source, COUNT(*) AS completed_orders, SUM(orders.total_amount) AS purchase_value, COUNT(DISTINCT customers.id) AS customers")
            ->groupByRaw($sourceExpression)->orderByDesc('purchase_value')->orderBy('source')->get()
            ->map(fn ($row) => (array) $row + ['label' => Customer::SOURCES[$row->source] ?? match ($row->source) {
                'guest' => 'Guest / unlinked',
                'unknown' => 'Source not specified',
                default => $row->source,
            }]);

        // Include deleted customers to keep historical sales represented accurately.
        $leaderboard = (clone $joined)->whereNotNull('customers.id')
            ->select('customers.id', 'customers.name', 'customers.source', 'customers.source_details', 'customers.deleted_at')
            ->selectRaw('COUNT(*) AS completed_orders, SUM(orders.total_amount) AS purchase_value')
            ->groupBy('customers.id', 'customers.name', 'customers.source', 'customers.source_details', 'customers.deleted_at');
        $primary = $filters['rank_by'] === 'orders' ? 'completed_orders' : 'purchase_value';
        $secondary = $filters['rank_by'] === 'orders' ? 'purchase_value' : 'completed_orders';
        $leaderboard = $leaderboard->orderByDesc($primary)->orderByDesc($secondary)->orderBy('customers.id')->paginate(10)->withQueryString();

        $daily = (clone $orders)->selectRaw('DATE(orders.created_at) AS date, COUNT(*) AS completed_orders, SUM(total_amount) AS purchase_value')
            ->groupByRaw('DATE(orders.created_at)')->get()->keyBy('date');
        $trend = [];
        for ($day = Carbon::parse($filters['from']); $day->lte(Carbon::parse($filters['to'])); $day->addDay()) {
            $key = $day->toDateString();
            $trend[] = ['date' => $key, 'purchase_value' => $daily[$key]->purchase_value ?? 0, 'completed_orders' => $daily[$key]->completed_orders ?? 0];
        }

        return compact('summary', 'sources', 'leaderboard', 'trend');
    }
}
