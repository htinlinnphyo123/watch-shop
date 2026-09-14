<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Customer;
use App\Models\PreOrder;
use App\Models\Product;
use App\Models\ProductItem;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PreOrderController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'type' => ['nullable', Rule::in(['pre_order', 'reservation'])],
            'status' => ['nullable', Rule::in(['pending', 'ordered', 'sold_out', 'completed', 'cancelled'])],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ]);

        return Inertia::render('PreOrders/Index', [
            'filters' => $filters,
            'preOrders' => PreOrder::with(['customer:id,name,phone', 'brand:id,name', 'user:id,name', 'fileUploads', 'reservedItem:id,product_id,system_unique_id,serial_number,status',
                'product' => fn ($query) => $query->select(['id', 'brand_id', 'name', 'model_number', 'deleted_at'])
                    ->withCount(['items as available_stock' => fn ($items) => $items->where('status', 'available')]),
            ])
                ->when($filters['type'] ?? null, fn ($query, $value) => $query->where('type', $value))
                ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
                ->when($filters['brand_id'] ?? null, fn ($query, $value) => $query->where('brand_id', $value))
                ->when($filters['customer_id'] ?? null, fn ($query, $value) => $query->where('customer_id', $value))
                ->when($filters['product_id'] ?? null, fn ($query, $value) => $query->where('product_id', $value))
                ->latest('id')->paginate(20)->withQueryString(),
            'availableItems' => ProductItem::where('status', 'available')->whereHas('product')->get(['id', 'product_id', 'system_unique_id', 'serial_number']),
            'products' => Product::orderBy('name')->get(['id', 'brand_id', 'name', 'model_number']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone']),
        ]);
    }

    public function store(Request $request)
    {
        app(ReservationService::class)->save($this->validated($request), $request->user()->id);

        return to_route('pre-orders.index')->with('success', 'Pre-order added.');
    }

    public function update(Request $request, PreOrder $preOrder)
    {
        app(ReservationService::class)->save($this->validated($request, $preOrder), $request->user()->id, $preOrder);

        return back()->with('success', 'Pre-order updated.');
    }

    private function validated(Request $request, ?PreOrder $preOrder = null): array
    {
        $type = $request->input('type', $preOrder?->type ?? 'pre_order');

        return $request->validate([
            'type' => ['sometimes', 'required', Rule::in(['pre_order', 'reservation'])],
            'product_item_id' => [Rule::requiredIf($type === 'reservation'), 'nullable', 'integer', 'exists:product_items,id'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(function ($query) use ($preOrder) {
                $query->whereNull('deleted_at')->orWhere('id', $preOrder?->customer_id);
            })],
            'brand_id' => ['required', 'integer', Rule::exists('brands', 'id')->where(function ($query) use ($preOrder) {
                $query->whereNull('deleted_at')->orWhere('id', $preOrder?->brand_id);
            })],
            'product_id' => [Rule::requiredIf($type === 'reservation'), 'nullable', 'integer', Rule::exists('products', 'id')->where(function ($query) use ($request, $preOrder) {
                $query->where('brand_id', $request->input('brand_id'))
                    ->where(fn ($active) => $active->whereNull('deleted_at')->orWhere('id', $preOrder?->product_id));
            })],
            'status' => ['required', Rule::in($type === 'reservation' ? ['pending', 'completed', 'cancelled'] : ['pending', 'ordered', 'sold_out', 'cancelled'])],
            'watch_details' => ['nullable', 'string', 'max:255'],
            'amount_paid' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'remark' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
