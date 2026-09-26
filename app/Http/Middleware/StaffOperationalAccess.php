<?php

namespace App\Http\Middleware;

use App\Models\Order;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffOperationalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role !== 'staff') {
            return $next($request);
        }

        if ($request->routeIs('dashboard')) {
            return redirect()->route('pos.index');
        }

        abort_unless($request->routeIs(
            'profile.*', 'wallet.*', 'customers.index', 'customers.store',
            'pos.*', 'orders.index', 'orders.show', 'orders.history', 'orders.files.*',
            'orders.approve', 'orders.cancel', 'low-stock-notifications.index',
            'watch-services.*', 'pre-orders.*',
        ), 403);

        // Includes invoice, history, attachments, cancellation and approval URLs.
        $order = $request->route('order');
        if ($order instanceof Order) {
            abort_unless((int) $order->user_id === (int) $request->user()->id, 403);
        }
        // All POS lookups accept order_id to expose original sold units/prices.
        if ($request->routeIs('pos.*') && $request->filled('order_id')) {
            $request->validate(['order_id' => 'integer']);
            abort_unless(Order::whereKey($request->order_id)->where('user_id', $request->user()->id)->exists(), 403);
        }

        return $next($request);
    }
}
