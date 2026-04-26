<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class POSController extends Controller
{
    public function index()
    {
        return Inertia::render('POS/Index', [
            'products' => Product::with(['brand', 'categories', 'customerGroups', 'items' => function ($q) {
                $q->where('status', 'available');
            }])->whereHas('items', function ($q) {
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
            'customer_id'       => 'nullable|exists:customers,id',
            'cart'              => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.item_id'    => 'nullable|exists:product_items,id',
            'cart.*.quantity'   => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $settings      = \App\Models\Setting::pluck('value', 'key')->toArray();
            $subtotal      = 0;
            $orderItemsData = [];

            $customerGroup = null;
            if ($request->customer_id) {
                $customer      = Customer::with('group')->find($request->customer_id);
                $customerGroup = $customer ? $customer->group : null;
            }

            foreach ($request->cart as $cartLine) {
                $product = Product::with('customerGroups')->findOrFail($cartLine['product_id']);

                // ── Resolve the actual ProductItem record(s) ──────────────────
                if (!empty($cartLine['item_id'])) {
                    // Specific unit pinned by the cashier (serial selected)
                    $items = ProductItem::where('id', $cartLine['item_id'])
                        ->where('product_id', $product->id)
                        ->where('status', 'available')
                        ->lockForUpdate()
                        ->get();

                    if ($items->isEmpty()) {
                        throw new \Exception("The selected unit for \"{$product->name}\" is no longer available.");
                    }
                } else {
                    // Generic: auto-pick oldest available units
                    $qty   = intval($cartLine['quantity'] ?? 1);
                    $items = ProductItem::where('product_id', $product->id)
                        ->where('status', 'available')
                        ->orderBy('created_at')
                        ->limit($qty)
                        ->lockForUpdate()
                        ->get();

                    if ($items->count() < $qty) {
                        throw new \Exception(
                            "Not enough stock for \"{$product->name}\". Requested: {$qty}, available: {$items->count()}."
                        );
                    }
                }

                // ── Convert to MMK ────────────────────────────────────────────
                $rate = 1;
                if ($product->currency && $product->currency !== 'MMK') {
                    $rateKey = strtolower($product->currency) . '_rate';
                    $rate    = floatval($settings[$rateKey] ?? 1);
                }
                $mmkPrice = floatval($product->price) * $rate;

                $finalItemPrice = $mmkPrice;
                if ($customerGroup) {
                    $override   = $product->customerGroups->where('id', $customerGroup->id)->first();
                    $percentage = $override ? $override->pivot->percentage : $customerGroup->percentage;
                    if ($percentage > 0) {
                        $finalItemPrice = $mmkPrice - ($mmkPrice * ($percentage / 100));
                    }
                }

                $subtotal += $finalItemPrice * $items->count();

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity'   => $items->count(),
                    'price'      => $finalItemPrice,
                    'item_ids'   => $items->pluck('id')->all(),
                ];
            }

            // Apply Top Level Discount
            $topLevelDiscount = \App\Models\TopLevelDiscount::where('amount', '<=', $subtotal)
                ->orderBy('amount', 'desc')
                ->first();

            if ($topLevelDiscount && $topLevelDiscount->percentage > 0) {
                $subtotal = $subtotal - ($subtotal * ($topLevelDiscount->percentage / 100));
            }

            $order = Order::create([
                'user_id'      => auth()->id(),
                'customer_id'  => $request->customer_id,
                'total_amount' => $subtotal,
                'status'       => 'completed',
                'order_number' => 'ORD-' . strtoupper(uniqid()),
            ]);

            foreach ($orderItemsData as $lineData) {
                $orderItem = $order->items()->create([
                    'product_id' => $lineData['product_id'],
                    'quantity'   => $lineData['quantity'],
                    'price'      => $lineData['price'],
                ]);

                // Link the resolved product_items to this order line and mark sold
                ProductItem::whereIn('id', $lineData['item_ids'])->update([
                    'status'        => 'sold',
                    'order_item_id' => $orderItem->id,
                ]);
            }

            DB::commit();
            return redirect()->route('orders.show', $order);
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->withErrors(['error' => 'Checkout failed: ' . $e->getMessage()]);
        }
    }
}
