<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\LowStockNotificationService;
use App\Services\OrderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class POSController extends Controller
{
    public function __construct(private readonly LowStockNotificationService $lowStockNotifications) {}

    public function index(Request $request)
    {
        $editingOrder = $this->editingOrder($request);
        $products = $this->productQuery(null, $editingOrder)->paginate(24);

        return Inertia::render('POS/Index', [
            'editingOrder' => $editingOrder ? $this->editPayload($editingOrder) : null,
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
            'kind' => 'nullable|in:watch,accessory',
            'page' => 'nullable|integer|min:1',
        ]);

        return response()->json(
            $this->productQuery($request->string('q')->trim()->toString(), $this->editingOrder($request))
                ->when($request->filled('kind'), fn ($q) => $q->where('kind', $request->kind))->paginate(24)
        );
    }

    public function availableItems(Request $request, Product $product)
    {
        $editingOrder = $this->editingOrder($request);
        $originalLines = $editingOrder?->items()->get()->keyBy('id');

        return response()->json([
            'items' => $this->stockQuery($product->items(), $editingOrder)
                ->orderBy('created_at')
                ->get()->map(function ($item) use ($originalLines) {
                    $line = $originalLines?->get($item->order_item_id);
                    if ($line) {
                        $item->setAttribute('original_line_id', $line->id);
                        $item->setAttribute('pos_price_mmk', (float) $line->price);
                    }

                    return $item;
                }),
        ]);
    }

    public function scan(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:255',
        ]);

        $code = trim($validated['code']);
        $editingOrder = $this->editingOrder($request);
        $item = $this->stockQuery(ProductItem::query(), $editingOrder)
            ->where(function ($query) use ($code) {
                $query->where('serial_number', $code)
                    ->orWhere('system_unique_id', $code);
            })
            ->first();

        $product = $item
            ? $this->productQuery(null, $editingOrder)->whereKey($item->product_id)->first()
            : $this->productQuery(null, $editingOrder)->where('barcode', $code)->first();

        abort_unless($product, 404, 'No available product matches that code.');

        $originalLine = $editingOrder && $item ? $editingOrder->items()->whereKey($item->order_item_id)->first() : null;
        if ($originalLine) {
            $product->setAttribute('pos_price_mmk', $originalLine->price);
        }

        return response()->json([
            'original_line_id' => $originalLine?->id,
            'product' => $product,
            'item' => $item,
        ]);
    }

    public function checkout(Request $request)
    {
        return $this->saveOrder($request);
    }

    public function update(Request $request, Order $order)
    {
        return $this->saveOrder($request, $order);
    }

    private function saveOrder(Request $request, ?Order $editingOrder = null)
    {
        $request->validate([
            'edit_version' => $editingOrder ? 'required|integer|min:0' : 'nullable|integer',
            'customer_id' => 'nullable|exists:customers,id',
            'status' => 'sometimes|required|in:pending,completed',
            'delivery_code' => 'nullable|string|max:255',
            'remark' => 'nullable|string|max:5000',
            'money_transfer_amount' => 'nullable|numeric|min:0|max:999999999999|decimal:0,2',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_method' => 'required_without:payments|nullable|in:cash,kbz_pay,card,transfer,cb_pay,aya_pay,other',
            'amount_paid' => 'required_without:payments|nullable|numeric|min:0|max:999999999999|decimal:0,2',
            'payments' => 'sometimes|required|array|list|min:1|max:7',
            'payments.*' => 'required|array:method,amount',
            'payments.*.method' => 'required|in:cash,kbz_pay,card,transfer,cb_pay,aya_pay,other|distinct',
            'payments.*.amount' => 'required|numeric|min:0|max:999999999999|decimal:0,2',
            'cart' => 'required|array|min:1',
            'cart.*.product_id' => 'required|exists:products,id',
            'cart.*.item_id' => 'nullable|exists:product_items,id|distinct',
            'cart.*.original_line_id' => 'nullable|integer',
            'cart.*.quantity' => 'nullable|integer|min:1',
        ]);

        try {
            DB::beginTransaction();
            $auditBefore = null;
            $originalLines = collect();
            $originalProducts = collect();
            if ($editingOrder) {
                $editingOrder = Order::whereKey($editingOrder->id)->lockForUpdate()->firstOrFail();
                if (! in_array($editingOrder->status, ['completed', 'pending'], true)) {
                    throw ValidationException::withMessages(['error' => 'Only completed or pending orders can be edited.']);
                }
                if ($editingOrder->edit_version !== (int) $request->edit_version) {
                    throw ValidationException::withMessages(['error' => 'This order changed since you opened it. Cancel and reopen Edit Order to load the latest version.']);
                }
                $originalLines = $editingOrder->items()->with(['soldItems' => fn ($query) => $query->lockForUpdate()])->get()->keyBy('id');
                $originalProducts = $originalLines->pluck('product_id');
                $auditBefore = app(\App\Services\OrderAuditService::class)->before($editingOrder);
                if ($editingOrder->status === 'completed') {
                    foreach ($originalLines as $line) {
                        if ($line->soldItems->count() !== $line->quantity || $line->soldItems->contains(fn ($item) => $item->status !== 'sold')) {
                            throw ValidationException::withMessages(['error' => 'The original stock records have changed or are incomplete. This order cannot be edited safely.']);
                        }
                    }
                    // Release only this order's sold units inside the transaction.
                    ProductItem::whereIn('order_item_id', $originalLines->keys())->where('status', 'sold')
                        ->update(['status' => 'available', 'order_item_id' => null]);
                }
            }
            $status = $editingOrder?->status ?? $request->input('status', 'completed');
            if ($editingOrder && $request->has('status') && $request->status !== $status) {
                throw ValidationException::withMessages(['status' => 'Use the order approval or cancellation action to change its status.']);
            }
            $chosenIds = [];
            $pinnedIds = collect($request->cart)->pluck('item_id')->filter()->all();

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
                $originalLine = ! empty($cartLine['original_line_id']) ? $originalLines->get($cartLine['original_line_id']) : null;
                if (! empty($cartLine['original_line_id']) && (! $originalLine || $originalLine->product_id !== $product->id)) {
                    throw ValidationException::withMessages(['cart' => 'An original order line does not match this watch. Reopen the order and retry.']);
                }
                if ($originalLine && $editingOrder->status === 'completed' && ! $originalLine->soldItems->contains('id', $cartLine['item_id'] ?? null)) {
                    throw ValidationException::withMessages(['cart' => 'The original price belongs to a different watch unit.']);
                }

                if ($product->kind === 'accessory' && ! $product->is_active && ! $originalLine) {
                    throw ValidationException::withMessages(['cart' => 'This accessory is inactive and cannot be sold.']);
                }

                // ── Resolve the actual ProductItem record(s) ──────────────────
                if (! empty($cartLine['item_id'])) {
                    // Specific unit pinned by the cashier (serial selected)
                    $items = ProductItem::whereNotIn('id', $chosenIds)->where('id', $cartLine['item_id'])
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
                    $items = ProductItem::whereNotIn('id', array_merge($chosenIds, $pinnedIds))->where('product_id', $product->id)
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
                $mmkPrice = $originalLine ? (float) $originalLine->price : floatval($product->price) * $rate;
                $chosenIds = array_merge($chosenIds, $items->pluck('id')->all());

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
            $paymentDetails = (new OrderPaymentService)->summarize(
                $request->input('payments', [[
                    'method' => $request->payment_method,
                    'amount' => $request->amount_paid,
                ]]),
                $totalAmount,
                $status === 'pending',
            );

            $order = $editingOrder ?? new Order(['user_id' => auth()->id(), 'order_number' => 'ORD-'.strtoupper(uniqid()), 'status' => $status]);
            $order->fill([
                'customer_id' => $request->customer_id,
                'delivery_code' => $request->input('delivery_code', $editingOrder?->delivery_code),
                'remark' => $request->input('remark', $editingOrder?->remark),
                'money_transfer_amount' => $request->input('money_transfer_amount', $editingOrder?->money_transfer_amount),
                'discount_percentage' => round($discountPercentage, 2),
                ...$paymentDetails,
                'total_amount' => $totalAmount,
                'edit_version' => $editingOrder ? $editingOrder->edit_version + 1 : 0,
            ]);

            $order->save();
            if ($editingOrder) {
                $order->items()->delete();
            }

            foreach ($orderItemsData as $lineData) {
                $orderItem = $order->items()->create([
                    'product_id' => $lineData['product_id'],
                    'quantity' => $lineData['quantity'],
                    'price' => $lineData['price'],
                ]);

                // Link the resolved product_items to this order line and mark sold
                if ($order->status === 'completed') {
                    ProductItem::whereIn('id', $lineData['item_ids'])->update([
                        'status' => 'sold',
                        'order_item_id' => $orderItem->id,
                    ]);
                }
            }

            foreach (collect($orderItemsData)->pluck('product_id')->merge($originalProducts)->unique() as $productId) {
                $this->lowStockNotifications->sync(Product::findOrFail($productId));
            }

            app(\App\Services\OrderAuditService::class)->record($order, $editingOrder ? 'edited' : 'created', $auditBefore);
            DB::commit();

            return redirect()->route('orders.show', $order);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            return redirect()->back()->withErrors(['error' => ($editingOrder ? 'Order update failed: ' : 'Checkout failed: ').$e->getMessage()]);
        }
    }

    private function editingOrder(Request $request): ?Order
    {
        $request->validate(['order_id' => 'nullable|integer|exists:orders,id']);
        if (! $request->filled('order_id')) {
            return null;
        }
        $order = Order::findOrFail($request->order_id);
        abort_unless(in_array($order->status, ['completed', 'pending'], true), 422, 'Only completed or pending orders can be edited.');

        return $order;
    }

    private function stockQuery($query, ?Order $order)
    {
        return $query->where(function ($stock) use ($order) {
            $stock->where('status', 'available');
            if ($order && $order->status === 'completed') {
                $stock->orWhere(function ($owned) use ($order) {
                    $owned->where('status', 'sold')->whereIn('order_item_id', $order->items()->select('id'));
                });
            }
        });
    }

    private function editPayload(Order $order): array
    {
        $order->load(['items.product', 'items.soldItems']);
        $cart = [];
        foreach ($order->items as $line) {
            abort_unless($line->product, 422, 'An original watch is no longer in the catalog. Restore it before editing this order.');
            if ($order->status === 'completed') {
                abort_unless($line->soldItems->count() === $line->quantity && $line->soldItems->every(fn ($unit) => $unit->status === 'sold'), 422, 'The original stock records are incomplete or have changed.');
            }
            $product = $line->product->only(['id', 'name', 'model_number', 'barcode', 'images', 'currency', 'price', 'discount']);
            $product['pos_price_mmk'] = (float) $line->price;
            $product['available_items_count'] = $this->stockQuery($line->product->items(), $order)->count();
            if ($order->status === 'completed') {
                foreach ($line->soldItems as $unit) {
                    $cart[] = ['product' => $product, 'item_id' => $unit->id, 'serial_number' => $unit->serial_number ?: $unit->system_unique_id,
                        'system_unique_id' => $unit->system_unique_id, 'qty' => 1, 'original_line_id' => $line->id];
                }
            } else {
                $cart[] = ['product' => $product, 'item_id' => null, 'serial_number' => null, 'qty' => $line->quantity, 'original_line_id' => $line->id];
            }
        }

        return [
            'id' => $order->id, 'order_number' => $order->order_number, 'status' => $order->status,
            'edit_version' => $order->edit_version, 'customer_id' => $order->customer_id,
            'delivery_code' => $order->delivery_code, 'remark' => $order->remark,
            'money_transfer_amount' => $order->money_transfer_amount,
            'discount_percentage' => $order->discount_percentage, 'cart' => $cart,
            'payments' => $order->payments ?: [['method' => $order->payment_method ?: 'cash', 'amount' => $order->amount_paid ?? $order->total_amount]],
        ];
    }

    private function productQuery(?string $search = null, ?Order $editingOrder = null)
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
                'kind',
                'accessory_attributes',
            ])
            ->where(function ($q) use ($editingOrder) {
                $q->where('kind', 'watch')->orWhere('is_active', true);
                if ($editingOrder) {
                    $q->orWhereIn('id', $editingOrder->items()->select('product_id'));
                }
            })
            ->withCount(['items as available_items_count' => function ($query) use ($editingOrder) {
                $this->stockQuery($query, $editingOrder);
            }])
            ->whereHas('items', function ($query) use ($editingOrder) {
                $this->stockQuery($query, $editingOrder);
            })
            ->when($search, function ($query, $search) use ($editingOrder) {
                $term = '%'.mb_strtolower($search).'%';

                $query->where(function ($query) use ($term, $editingOrder) {
                    $query->whereRaw('LOWER(name) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(model_number) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$term])
                        ->orWhereHas('brand', function ($brandQuery) use ($term) {
                            $brandQuery->whereRaw('LOWER(name) LIKE ?', [$term]);
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($term, $editingOrder) {
                            $this->stockQuery($itemQuery, $editingOrder)
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
