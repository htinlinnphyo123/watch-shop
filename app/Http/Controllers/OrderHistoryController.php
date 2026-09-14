<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderAudit;
use App\Services\OrderAuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderHistoryController extends Controller
{
    public function __invoke(Request $request, Order $order, OrderAuditService $audit)
    {
        $filters = $request->validate(['from' => 'nullable|integer|min:1', 'to' => 'nullable|integer|min:1']);
        $query = OrderAudit::where('order_id', $order->id);
        $versions = (clone $query)->orderByDesc('version')->get(['version', 'event', 'actor_name', 'actor_type', 'created_at']);
        $toVersion = $filters['to'] ?? $versions->first()?->version;
        $to = $toVersion ? (clone $query)->where('version', $toVersion)->firstOrFail() : null;
        $fromVersion = $filters['from'] ?? ($toVersion > 1 ? $toVersion - 1 : null);
        $from = $fromVersion ? (clone $query)->where('version', $fromVersion)->firstOrFail() : null;

        return Inertia::render('Orders/History', [
            'order' => $order->only('id', 'order_number'),
            'versions' => $versions,
            'history' => (clone $query)->orderByDesc('version')->paginate(10, ['version', 'event', 'actor_name', 'actor_type', 'created_at', 'changes'])->withQueryString(),
            'from' => $from,
            'to' => $to,
            'changes' => $to ? $audit->compare($from?->snapshot ?? [], $to->snapshot) : [],
            'hasOriginal' => $versions->last()?->event === 'created',
            'currentSnapshot' => $versions->isEmpty() ? $audit->snapshot($order) : null,
        ]);
    }
}
