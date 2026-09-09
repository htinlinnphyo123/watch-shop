<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\LowStockNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class POSController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications) {}

    public function index()
    {
        $products = $this->productQuery()->paginate(24);

        return Inertia::render('POS/Index', [
            'products' => $products->items(),
            'productsPagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
            'customers' => Customer::with('group')->get(),
        ]);
    }

    public function products(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        return response()->json(
            $this->productQuery($request->string('q')->trim()->toString())->paginate(24)
        );
    }

    public function availableItems(Product $product)
    {
        return response()->json([
            'items' => $product->items()
                ->where('status', 'available')
                ->orderBy('created_at')
                ->get(),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $code = trim($validated['code']);
        $item = ProductItem::query()
            ->where('status', 'available')
            ->where(function ($query) use ($code) {
                $query->where('serial_number', $code)
                    ->orWhere('system_unique_id', $code);
            })
            ->first();

        $product = $item
            ? $this->productQuery()->whereKey($item->product_id)->first()
            : $this->productQuery()->where('barcode', $code)->first();

        abort_unless($product, 404, 'No available watch matches that code.');

        return response()->json([
            'product' => $product,
            'item' => $item,
        ]);
    }

    public function checkout(Request $request)
    {
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required|in:cash,card,transfer',
            'amount_paid' => 'required|numeric|min:0',
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.item_id' => 'nullable|exists:product_items,id',
            'cart.*.quantity' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $settings = \App\Models\Setting::pluck('value', 'key')->toArray();
            $subtotal = 0;
            $watchDiscountAmount = 0;
            $orderItemsData = [];

            $customerGroup = null;
            if ($request->customer_id) {
                $customer = Customer::with('group')->find($request->customer_id);
                $customerGroup = $customer ? $customer->group : null;
            }

            foreach ($request->cart as $cartLine) {
                $product = Product::findOrFail($cartLine['product_id']);

                // ── Resolve the actual ProductItem record(s) ──────────────────
                if (! empty($cartLine['item_id'])) {
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
                    $qty = intval($cartLine['quantity'] ?? 1);
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
                    $rateKey = strtolower($product->currency).'_rate';
                    $rate = floatval($settings[$rateKey] ?? 1);
                }
                $mmkPrice = floatval($product->price) * $rate;

                $lineSubtotal = $mmkPrice * $items->count();
                $subtotal += $lineSubtotal;
                $watchDiscountAmount += $lineSubtotal * (floatval($product->discount ?? 0) / 100);

                $orderItemsData[] = [
                    'product_id' => $product->id,
                    'quantity' => $items->count(),
                    'price' => $mmkPrice,
                    'item_ids' => $items->pluck('id')->all(),
                ];
            }

            // The submitted value is the salesperson's final override. Older
            // clients that omit it still receive the same authoritative default.
            $discountPercentage = $request->filled('discount_percentage')
                ? floatval($request->discount_percentage)
                : ($customerGroup
                    ? floatval($customerGroup->percentage)
                    : ($subtotal > 0 ? ($watchDiscountAmount / $subtotal) * 100 : 0));
            $discountPercentage = max(0, min(100, $discountPercentage));
            $totalAmount = round($subtotal * (1 - ($discountPercentage / 100)), 2);
            $amountPaid = round(floatval($request->amount_paid), 2);

            if ($amountPaid < $totalAmount) {
                DB::rollBack();

                return redirect()->back()->withErrors([
                    'amount_paid' => 'Amount paid is '.number_format($totalAmount - $amountPaid, 2).' Ks short of the total due.',
                ]);
            }

            $order = Order::create([
                'user_id' => auth()->id(),
                'customer_id' => $request->customer_id,
                'discount_percentage' => round($discountPercentage, 2),
                'payment_method' => $request->payment_method,
                'amount_paid' => $amountPaid,
                'total_amount' => $totalAmount,
                'status' => 'completed',
                'order_number' => 'ORD-'.strtoupper(uniqid()),
            ]);

            foreach ($orderItemsData as $lineData) {
                $orderItem = $order->items()->create([
                    'product_id' => $lineData['product_id'],
                    'quantity' => $lineData['quantity'],
                    'price' => $lineData['price'],
                ]);

                // Link the resolved product_items to this order line and mark sold
                ProductItem::whereIn('id', $lineData['item_ids'])->update([
                    'status' => 'sold',
                    'order_item_id' => $orderItem->id,
                ]);
            }

            foreach (collect($orderItemsData)->pluck('product_id')->unique() as $productId) {
                $this->lowStockNotifications->sync(Product::findOrFail($productId));
            }

            DB::commit();

            return redirect()->route('orders.show', $order);
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['error' => 'Checkout failed: '.$e->getMessage()]);
        }
    }

    private function productQuery(?string $search = null)
    {
        return Product::query()
            ->select([
                'id',
                'name',
                'model_number',
                'barcode',
                'images',
                'currency',
                'price',
                'discount',
            ])
            ->withCount(['items as available_items_count' => function ($query) {
                $query->where('status', 'available');
            }])
            ->whereHas('items', function ($query) {
                $query->where('status', 'available');
            })
            ->when($search, function ($query, $search) {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($term) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(model_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$term])
                        ->orWhereHas('brand', function ($brandQuery) use ($term) {
                            $brandQuery->whereRaw('LOWER(name) LIKE ?', [$term]);
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($term) {
                            $itemQuery->where('status', 'available')
                                ->where(function ($itemQuery) use ($term) {
                                    $itemQuery->whereRaw('LOWER(serial_number) LIKE ?', [$term])
                                        ->orWhereRaw('LOWER(system_unique_id) LIKE ?', [$term]);
                                });
                        });
                });
            })
            ->orderBy('name');
    }
}
