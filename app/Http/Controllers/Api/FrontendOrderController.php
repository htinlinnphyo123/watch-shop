<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Setting;

class FrontendOrderController extends Controller
{
    public function preview(Request $request)
    {
        $request->validate([
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.count' => 'required|integer|min:1',
        ]);

        try {
            $user = $request->user();
            
            $customerGroup = null;
            if ($user instanceof \App\Models\Customer) {
                $customerGroup = $user->group;
            } elseif ($user && $user->customer) {
                $customerGroup = $user->customer->group;
            }

            $settings = Setting::pluck('value', 'key')->toArray();
            $subtotal = 0;
            $items = [];

            foreach ($request->cart as $cartLine) {
                $product = Product::with('customerGroups')->findOrFail($cartLine['id']);
                $qty = (int) $cartLine['count'];
                // Check stock availability
                $availableStock = \App\Models\ProductItem::where('product_id', $product->id)
                    ->where('status', 'available')
                    ->count();

                if ($availableStock < $qty) {
                    throw new \Exception("Not enough stock for \"{$product->name}\". Requested: {$qty}, Available: {$availableStock}.");
                }

                // Convert to MMK
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

                $subtotal += $finalItemPrice * $qty;

                $items[] = [
                    'product_id' => $product->id,
                    'name'       => $product->name,
                    'quantity'   => $qty,
                    'unit_price' => $finalItemPrice,
                    'line_total' => $finalItemPrice * $qty,
                ];
            }

            $originalSubtotal = $subtotal;
            $discountAmount = 0;
            $discountPercentage = 0;

            // Apply Top Level Discount
            $topLevelDiscount = \App\Models\TopLevelDiscount::where('amount', '<=', $subtotal)
                ->orderBy('amount', 'desc')
                ->first();

            if ($topLevelDiscount && $topLevelDiscount->percentage > 0) {
                $discountPercentage = floatval($topLevelDiscount->percentage);
                $discountAmount = $subtotal * ($discountPercentage / 100);
                $subtotal = $subtotal - $discountAmount;
            }

            return response()->json([
                'success' => true,
                'preview' => [
                    'items'                     => $items,
                    'subtotal'                  => $originalSubtotal,
                    'top_level_discount_amount' => $discountAmount,
                    'top_level_discount_percent'=> $discountPercentage,
                    'final_total_amount'        => $subtotal,
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Preview failed: ' . $e->getMessage()], 500);
        }
    }

    public function store(Request $request)
    {
        $request->validate([
            'cart' => 'required|array|min:1',
            'cart.*.id' => 'required|exists:products,id',
            'cart.*.count' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $user = $request->user();
            
            // Resolve correct customer ID
            $customerId = null;
            $customerGroup = null;
            if ($user instanceof \App\Models\Customer) {
                $customerId = $user->id;
                $customerGroup = $user->group;
            } elseif ($user->customer) {
                $customerId = $user->customer->id;
                $customerGroup = $user->customer->group;
            } else {
                // If it's an admin user that has NO attached customer, fallback to their user->id as just a placeholder or throw error
                // The prompt says "restrictly set to customer_id"
                return response()->json(['error' => 'You must be a valid customer to place an order.'], 403);
            }

            $settings = Setting::pluck('value', 'key')->toArray();
            $subtotal = 0;
            $orderItemsData = [];

            foreach ($request->cart as $cartLine) {
                $product = Product::with('customerGroups')->findOrFail($cartLine['id']);
                $qty = (int) $cartLine['count'];
                // Check stock availability
                $availableStock = \App\Models\ProductItem::where('product_id', $product->id)
                    ->where('status', 'available')
                    ->count();

                if ($availableStock < $qty) {
                    throw new \Exception("Not enough stock for \"{$product->name}\". Requested: {$qty}, Available: {$availableStock}.");
                }

                // Convert to MMK
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

                $subtotal += $finalItemPrice * $qty;

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity'   => $qty,
                    'price'      => $finalItemPrice,
                ];
            }

            // Apply Top Level Discount
            $topLevelDiscount = \App\Models\TopLevelDiscount::where('amount', '<=', $subtotal)
                ->orderBy('amount', 'desc')
                ->first();

            if ($topLevelDiscount && $topLevelDiscount->percentage > 0) {
                $subtotal = $subtotal - ($subtotal * ($topLevelDiscount->percentage / 100));
            }

            // Generate order with 'pending' status
            $order = Order::create([
                'customer_id'  => $customerId,
                'total_amount' => $subtotal,
                'status'       => 'pending',
                'order_number' => 'ORD-' . strtoupper(uniqid()),
            ]);

            foreach ($orderItemsData as $lineData) {
                $order->items()->create([
                    'product_id' => $lineData['product_id'],
                    'quantity'   => $lineData['quantity'],
                    'price'      => $lineData['price'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully and is pending approval.',
                'order'   => $order->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Checkout failed: ' . $e->getMessage()], 500);
        }
    }
}
