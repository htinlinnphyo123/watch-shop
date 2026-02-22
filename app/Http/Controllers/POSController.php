<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class POSController extends Controller
{
    public function index()
    {
        return Inertia::render('POS/Index', [
            'products' => Product::with(['brand', 'category', 'items' => function($q) {
                $q->where('status', 'available');
            }])->whereHas('items', function($q) {
                $q->where('status', 'available');
            })->get(),
            'customers' => Customer::with('group')->get(),
            'categories' => Category::all(),
            'brands' => Brand::all(),
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'items' => 'required|array',
            'items.*.id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        // Simple checkout logic (mockup)
        try {
            DB::beginTransaction();
            
            $order = Order::create([
                'user_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'total_amount' => 0, // Calculate loop
                'status' => 'completed',
                'order_number' => 'ORD-' . time(),
            ]);

            // Logic to calculate total and create items would go here
            // For now, just a placeholder success

            DB::commit();
            return redirect()->route('orders.show', $order);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Checkout failed']);
        }
    }
}
