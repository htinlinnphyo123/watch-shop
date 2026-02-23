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
            'cart' => 'required|array',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.serial_number' => 'required|exists:product_items,serial_number',
        ]);

        try {
            DB::beginTransaction();
            
            $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
            $subtotal = 0;
            $orderItemsData = [];
            $productItemIds = [];

            foreach ($request->cart as $cartItem) {
                $product = Product::find($cartItem['product_id']);
                $productItem = \App\Models\ProductItem::where('serial_number', $cartItem['serial_number'])
                    ->where('product_id', $product->id)
                    ->lockForUpdate()
                    ->first();

                if (!$productItem || $productItem->status !== 'available') {
                    throw new \Exception("Serial number {$cartItem['serial_number']} is no longer available.");
                }

                $rate = 1;
                if ($product->currency && $product->currency !== 'MMK') {
                    $rateKey = strtolower($product->currency) . '_rate';
                    $rate = floatval($settings[$rateKey] ?? 1);
                }
                
                $mmkPrice = floatval($product->price) * $rate;
                $subtotal += $mmkPrice;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $mmkPrice,
                ];

                $productItemIds[] = $productItem->id;
            }

            // Apply Discount
            $total_amount = $subtotal;
            if ($request->customer_id) {
                $customer = Customer::with('group')->find($request->customer_id);
                if ($customer && $customer->group) {
                    $discount = $subtotal * ($customer->group->percentage / 100);
                    $total_amount -= $discount;
                }
            }
            
            $order = Order::create([
                'user_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'total_amount' => $total_amount,
                'status' => 'completed',
                'order_number' => 'ORD-' . strtoupper(uniqid()),
            ]);

            foreach ($orderItemsData as $item) {
                $order->items()->create($item);
            }

            // Mark items as sold
            \App\Models\ProductItem::whereIn('id', $productItemIds)->update(['status' => 'sold']);

            DB::commit();
            return redirect()->route('orders.show', $order);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Checkout failed: ' . $e->getMessage()]);
        }
    }
}
