<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAudit;
use App\Models\User;

class OrderAuditService
{
    public function snapshot(Order $order): array
    {
        $order = $order->fresh(['customer', 'items.product' => fn ($query) => $query->withTrashed(), 'items.soldItems', 'fileUploads']);
        $watches = [];
        foreach ($order->items as $line) {
            $base = [
                'product_id' => $line->product_id, 'name' => $line->product?->name ?? 'Deleted watch',
                'model' => $line->product?->model_number, 'unit_price' => number_format((float) $line->price, 2, '.', ''),
            ];
            foreach ($line->soldItems as $unit) {
                $watches['unit:'.$unit->id] = [...$base, 'unit_id' => $unit->id, 'system_code' => $unit->system_unique_id, 'serial' => $unit->serial_number, 'quantity' => 1];
            }
            $remaining = $line->quantity - $line->soldItems->count();
            if ($remaining > 0) {
                $key = 'product:'.$line->product_id.':'.$base['unit_price'];
                $watches[$key] = [...$base, 'unit_id' => null, 'system_code' => null, 'serial' => null,
                    'quantity' => ($watches[$key]['quantity'] ?? 0) + $remaining];
            }
        }
        ksort($watches);
        $payments = [];
        foreach ($order->payments ?: ($order->amount_paid !== null ? [['method' => $order->payment_method ?: 'unrecorded', 'amount' => $order->amount_paid]] : []) as $payment) {
            $payments[$payment['method']] = number_format((float) $payment['amount'], 2, '.', '');
        }
        ksort($payments);
        $attachments = $order->fileUploads->sortBy('id')->mapWithKeys(fn ($file) => [$file->id => [
            'id' => $file->id, 'name' => $file->name, 'size' => $file->size, 'mime_type' => $file->mime_type,
        ]])->all();

        return [
            'order_number' => $order->order_number, 'status' => $order->status,
            'customer' => ['id' => $order->customer_id, 'name' => $order->customer?->name ?? ($order->customer_id ? 'Deleted customer' : 'Walk-in Customer')],
            'discount_percentage' => number_format((float) $order->discount_percentage, 2, '.', ''),
            'total_amount' => number_format((float) $order->total_amount, 2, '.', ''),
            'amount_paid' => $order->amount_paid,
            'delivery_code' => $order->delivery_code,
            'remark' => $order->remark,
            'money_transfer_amount' => $order->money_transfer_amount,
            'change' => number_format(max(0, (float) $order->amount_paid - (float) $order->total_amount), 2, '.', ''),
            'watches' => $watches, 'payments' => $payments, 'attachments' => $attachments,
        ];
    }

    /** Call before changing an order, inside the mutation's database transaction. */
    public function before(Order $order): array
    {
        Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $snapshot = $this->snapshot($order);
        if (! OrderAudit::where('order_id', $order->id)->exists()) {
            $this->append($order, 'baseline', $snapshot, [], null);
        }

        return $snapshot;
    }

    public function record(Order $order, string $event, ?array $before = null): void
    {
        Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        $snapshot = $this->snapshot($order);
        $this->append($order, $event, $snapshot, $this->compare($before ?? [], $snapshot), auth()->user());
    }

    private function append(Order $order, string $event, array $snapshot, array $changes, $actor): void
    {
        OrderAudit::create([
            'order_id' => $order->id, 'version' => (OrderAudit::where('order_id', $order->id)->max('version') ?? 0) + 1,
            'event' => $event, 'actor_id' => $actor?->id,
            'actor_type' => $actor ? ($actor instanceof User ? 'staff' : 'customer') : 'system',
            'actor_name' => $actor?->name ?? 'System', 'snapshot' => $snapshot, 'changes' => $changes, 'created_at' => now(),
        ]);
    }

    public function compare(array $before, array $after): array
    {
        $changes = [];
        foreach (['delivery_code' => 'Delivery code', 'remark' => 'Remark', 'money_transfer_amount' => 'Money transfer amount (Ks)'] as $key => $label) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = ['section' => 'Order', 'label' => $label, 'before' => $this->display($before[$key] ?? null), 'after' => $this->display($after[$key] ?? null)];
            }
        }
        foreach (['order_number' => 'Order number', 'status' => 'Status', 'customer' => 'Customer', 'discount_percentage' => 'Discount (%)', 'total_amount' => 'Total (Ks)', 'amount_paid' => 'Amount received (Ks)', 'change' => 'Change (Ks)'] as $key => $label) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[] = ['section' => 'Order', 'label' => $label, 'before' => $this->display($before[$key] ?? null), 'after' => $this->display($after[$key] ?? null)];
            }
        }
        foreach (['watches' => 'Watches', 'payments' => 'Payments (Ks)', 'attachments' => 'Attachments'] as $key => $section) {
            foreach (array_unique([...array_keys($before[$key] ?? []), ...array_keys($after[$key] ?? [])]) as $id) {
                $old = $before[$key][$id] ?? null;
                $new = $after[$key][$id] ?? null;
                if ($old !== $new) {
                    $changes[] = ['section' => $section, 'label' => $key === 'payments' ? $id : ($new['name'] ?? $old['name']),
                        'before' => $this->display($old), 'after' => $this->display($new)];
                }
            }
        }

        return $changes;
    }

    private function display($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_array($value)) {
            return (string) $value;
        }
        if (isset($value['quantity'])) {
            return $value['name'].' · Model: '.($value['model'] ?: '—').' · Code: '.($value['system_code'] ?: '—')
                .' · Serial: '.($value['serial'] ?: '—').' · Qty: '.$value['quantity'].' · '.$value['unit_price'].' Ks each';
        }
        if (isset($value['size'])) {
            return $value['name'].' · '.$value['size'].' bytes · '.$value['mime_type'];
        }

        return $value['name'].($value['id'] ? ' (#'.$value['id'].')' : '');
    }
}
