<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function index()
    {
        return Inertia::render('Orders/Index', [
            'orders' => Order::with(['customer', 'user', 'items.product'])->latest()->paginate(10),
        ]);
    }

    public function show(Order $order)
    {
        return Inertia::render('Orders/Show', [
            'order' => $order->load(['customer', 'user', 'items.product']),
        ]);
    }
}
