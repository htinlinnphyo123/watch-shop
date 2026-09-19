<?php

namespace App\Http\Controllers;

use App\Models\ProductItem;
use App\Models\WatchService;
use App\Models\WatchServiceExpense;
use App\Services\WatchWarrantyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WatchServiceController extends Controller
{
    private function options(): array
    {
        return ['statuses' => WatchService::STATUSES, 'decisions' => WatchService::DECISIONS, 'faults' => WatchService::FAULTS, 'today' => now()->toDateString()];
    }

    public function index(Request $request, WatchWarrantyService $warranty)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:255', 'unit_id' => 'nullable|integer',
            'origin' => 'nullable|in:inventory,external', 'search' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(array_keys(WatchService::STATUSES))],
        ]);
        $matches = [];
        if (! empty($filters['q'])) {
            $q = trim($filters['q']);
            $matches = $this->watches()->where(function ($query) use ($q) {
                $query->where('system_unique_id', $q)->orWhere('serial_number', $q)
                    ->orWhereHas('orderItem.order', fn ($order) => $order->where('order_number', $q));
            })->with(['product' => fn ($query) => $query->withTrashed()])->limit(25)->get()
                ->map(fn ($unit) => ['id' => $unit->id, 'name' => $unit->product->name, 'barcode' => $unit->system_unique_id, 'serial_number' => $unit->serial_number]);
        }
        $unit = ! empty($filters['unit_id']) && ($filters['origin'] ?? '') !== 'external' ? $this->watches()->findOrFail($filters['unit_id']) : null;
        $services = WatchService::query()->when($unit, fn ($query) => $query->where('product_item_id', $unit->id))
            ->when(($filters['origin'] ?? '') === 'external', fn ($query) => $query->where('watch_origin', 'external'))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('contact_name', 'like', '%'.$search.'%')->orWhere('contact_phone', 'like', '%'.$search.'%')
                        ->orWhere('warranty_snapshot->product_name', 'like', '%'.$search.'%')->orWhere('warranty_snapshot->serial_number', $search);
                    if (ctype_digit($search)) $query->orWhere('id', (int) $search);
                });
            })
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->latest('id')->paginate(10)->withQueryString();

        return Inertia::render('Warranty/Index', $this->options() + [
            'filters' => $filters, 'matches' => $matches, 'unitId' => $unit?->id,
            'check' => $unit ? $warranty->check($unit) : null, 'services' => $services,
        ]);
    }

    public function store(Request $request, WatchWarrantyService $warranty)
    {
        $request->mergeIfMissing(['watch_origin' => 'inventory', 'service_type' => 'repair']);
        $data = $request->validate([
            'watch_origin' => 'required|in:inventory,external', 'service_type' => 'required|in:repair,warranty',
            'product_item_id' => 'nullable|required_if:watch_origin,inventory|integer',
            'external_name' => 'nullable|required_if:watch_origin,external|string|max:255',
            'external_brand' => 'nullable|string|max:255', 'external_model' => 'nullable|string|max:255',
            'external_serial' => 'nullable|string|max:255',
            'contact_name' => 'required|string|max:255', 'contact_phone' => 'nullable|string|max:50',
            'issue_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'issue_description' => 'required|string|max:10000', 'notes' => 'nullable|string|max:10000',
        ] + $this->repairRules());
        $repair = $this->repairData($data);
        $service = DB::transaction(function () use ($request, $warranty, $data, $repair) {
            $unit = $data['watch_origin'] === 'inventory' ? $this->watches()->lockForUpdate()->findOrFail($data['product_item_id']) : null;
            $snapshot = $unit ? $warranty->check($unit, $data['issue_date']) : [
                'result' => 'unverified', 'label' => 'Outside watch — purchase not verified', 'checked_on' => $data['issue_date'],
                'product_name' => $data['external_name'], 'brand' => $data['external_brand'] ?? null, 'model' => $data['external_model'] ?? null,
                'serial_number' => $data['external_serial'] ?? null, 'barcode' => null,
                'purchase_date' => null, 'expires_on' => null, 'warranty_months' => null, 'warranty_type' => null,
                'order_id' => null, 'order_number' => null, 'customer_id' => null, 'customer_name' => null,
            ];
            if ($snapshot['purchase_date'] && $data['issue_date'] < $snapshot['purchase_date']) {
                throw ValidationException::withMessages(['issue_date' => 'The service issue date cannot be before the customer purchase date.']);
            }
            if ($unit && $this->openServices($unit->id)->exists()) {
                throw ValidationException::withMessages(['product_item_id' => 'This watch already has an open service record. Update that record first.']);
            }
            $service = WatchService::create(collect($data)->only(['watch_origin', 'service_type', 'contact_name', 'contact_phone', 'issue_date', 'issue_description'])->all() + $repair + [
                'product_item_id' => $unit?->id, 'order_id' => $snapshot['order_id'], 'customer_id' => $snapshot['customer_id'],
                'created_by' => $request->user()->id, 'status' => 'received',
                'coverage_decision' => $data['service_type'] === 'warranty' ? 'pending' : 'not_applicable', 'warranty_snapshot' => $snapshot,
            ]);
            $this->event($service, $request, $data['notes'] ?? 'Watch received.');
            return $service;
        });
        return to_route('watch-services.show', $service)->with('success', 'Repair / service record created.');
    }

    public function show(WatchService $watchService, WatchWarrantyService $warranty)
    {
        $total = $watchService->expenses()->whereNull('voided_at')->sum('amount');
        return Inertia::render('Warranty/Show', $this->options() + [
            'service' => $watchService->load(['events' => fn ($query) => $query->orderByDesc('id'), 'expenses' => fn ($query) => $query->orderByDesc('id')]),
            'currentCheck' => $watchService->unit ? $warranty->check($watchService->unit) : null,
            'expenseTotal' => $total,
            'expectedMargin' => $watchService->customer_charge !== null ? round($watchService->customer_charge - $total, 2) : null,
        ]);
    }

    public function update(Request $request, WatchService $watchService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(WatchService::STATUSES))],
            'coverage_decision' => ['required', Rule::in(array_keys(WatchService::DECISIONS))],
            'decision_notes' => 'nullable|required_if:coverage_decision,approved,rejected|string|max:10000',
            'notes' => 'required|string|max:10000', 'last_event_id' => 'required|integer',
        ] + $this->repairRules());
        DB::transaction(function () use ($request, $watchService, $data) {
            if ($watchService->product_item_id) ProductItem::withTrashed()->whereKey($watchService->product_item_id)->lockForUpdate()->firstOrFail();
            $service = WatchService::whereKey($watchService->id)->lockForUpdate()->firstOrFail();
            if ((int) $service->events()->max('id') !== (int) $data['last_event_id']) {
                throw ValidationException::withMessages(['notes' => 'Another administrator updated this service. Refresh the page before saving.']);
            }
            if ($service->product_item_id && ! in_array($data['status'], WatchService::CLOSED) && $this->openServices($service->product_item_id)->whereKeyNot($service->id)->exists()) {
                throw ValidationException::withMessages(['status' => 'Another service record is already open for this watch.']);
            }
            $repair = $this->repairData($data, $service);
            if (in_array($data['status'], ['sent_to_shop', 'external_repair', 'returned_from_shop', 'sent_to_company']) && $repair['repair_provider'] !== 'external') {
                throw ValidationException::withMessages(['repair_provider' => 'Choose an external repair provider and enter its name for this status.']);
            }
            $service->update(collect($data)->only(['status', 'coverage_decision', 'decision_notes'])->all() + $repair);
            $this->event($service, $request, $data['notes']);
        });
        return back()->with('success', 'Repair history updated.');
    }

    public function expense(Request $request, WatchService $watchService)
    {
        $data = $request->validate([
            'request_id' => 'required|uuid', 'amount' => 'required|numeric|decimal:0,2|min:0.01|max:999999999999.99',
            'expense_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'description' => 'required|string|max:1000', 'paid_to' => 'nullable|string|max:255',
        ]);
        DB::transaction(function () use ($watchService, $request, $data) {
            $service = WatchService::whereKey($watchService->id)->lockForUpdate()->firstOrFail();
            if ($existing = WatchServiceExpense::where('request_id', $data['request_id'])->first()) {
                abort_unless($existing->watch_service_id === $service->id, 409);
                return;
            }
            if ($data['expense_date'] < $service->issue_date->toDateString()) {
                throw ValidationException::withMessages(['expense_date' => 'The expense date cannot be before this service issue date.']);
            }
            $expense = $service->expenses()->create($data + ['created_by' => $request->user()->id]);
            $this->event($service, $request, "Expense #{$expense->id}: {$expense->amount} MMK — {$expense->description}".($expense->paid_to ? " (paid to {$expense->paid_to})" : ''), ['type' => 'expense_added', 'expense_id' => $expense->id]);
        });
        return back()->with('success', 'Repair expense recorded.');
    }

    public function voidExpense(Request $request, WatchService $watchService, WatchServiceExpense $expense)
    {
        abort_unless($expense->watch_service_id === $watchService->id, 404);
        $data = $request->validate(['void_reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($watchService, $expense, $request, $data) {
            $service = WatchService::whereKey($watchService->id)->lockForUpdate()->firstOrFail();
            $expense = WatchServiceExpense::whereKey($expense->id)->lockForUpdate()->firstOrFail();
            if ($expense->voided_at) return;
            $expense->update($data + ['voided_at' => now(), 'voided_by' => $request->user()->id]);
            $this->event($service, $request, "Expense #{$expense->id} cancelled ({$expense->amount} MMK): {$data['void_reason']}", ['type' => 'expense_cancelled', 'expense_id' => $expense->id]);
        });
        return back()->with('success', 'Expense cancelled and excluded from the total. The original entry remains in the history.');
    }

    private function repairRules(): array
    {
        return [
            'fault_type' => ['sometimes', 'required', Rule::in(array_keys(WatchService::FAULTS))],
            'diagnosis' => 'nullable|string|max:10000', 'repair_provider' => 'sometimes|required|in:in_house,external',
            'external_shop_name' => 'nullable|string|max:255', 'external_shop_phone' => 'nullable|string|max:50',
            'external_reference' => 'nullable|string|max:255', 'provider_notes' => 'nullable|string|max:10000',
            'billing_status' => 'sometimes|required|in:pending,free,chargeable',
            'customer_charge' => 'nullable|numeric|decimal:0,2|min:0|max:999999999999.99', 'charge_notes' => 'nullable|string|max:10000',
        ];
    }

    private function repairData(array $data, ?WatchService $service = null): array
    {
        $repair = array_replace(['fault_type' => 'unknown', 'repair_provider' => 'in_house', 'billing_status' => 'pending', 'customer_charge' => null], $service?->only(WatchService::REPAIR_FIELDS) ?? [], collect($data)->only(WatchService::REPAIR_FIELDS)->all());
        Validator::make($repair, [
            'external_shop_name' => 'nullable|required_if:repair_provider,external',
            'customer_charge' => 'nullable|required_if:billing_status,chargeable'.($repair['billing_status'] === 'chargeable' ? '|numeric|min:0.01' : ''),
            'charge_notes' => 'nullable|required_if:billing_status,free,chargeable',
        ])->validate();
        if ($repair['billing_status'] === 'free') $repair['customer_charge'] = '0.00';
        if ($repair['billing_status'] === 'pending') $repair['customer_charge'] = null;
        return $repair;
    }

    private function openServices(int $unitId)
    {
        return WatchService::where('product_item_id', $unitId)->whereNotIn('status', WatchService::CLOSED);
    }

    private function watches()
    {
        return ProductItem::withTrashed()->whereHas('product', fn ($query) => $query->withTrashed()->where('kind', 'watch'));
    }

    private function event(WatchService $service, Request $request, ?string $notes, array $activity = []): void
    {
        $service->events()->create([
            'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name,
            'status' => $service->status, 'coverage_decision' => $service->coverage_decision,
            'decision_notes' => $service->decision_notes, 'notes' => $notes,
            'repair_details' => $service->only(WatchService::REPAIR_FIELDS) + ['activity' => $activity],
        ]);
    }
}
