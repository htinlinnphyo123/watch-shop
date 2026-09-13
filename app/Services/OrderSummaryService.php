<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;

class OrderSummaryService
{
    public const METHODS = ['cash', 'kbz_pay', 'card', 'transfer'];

    public function filter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['date_from'])) {
            $query->whereDate('orders.created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('orders.created_at', '<=', $filters['date_to']);
        }
        if (! empty($filters['payment_type'])) {
            if ($filters['payment_type'] === 'split') {
                $query->where('payment_method', 'split');
            } else {
                $query->whereIn('payment_method', self::METHODS);
            }
        }
        if (! empty($filters['payment_method'])) {
            $method = $filters['payment_method'];
            $query->where(function (Builder $query) use ($method) {
                $query->where(function (Builder $legacy) use ($method) {
                    $legacy->whereNull('payments')->where('payment_method', $method);
                })->orWhere(function (Builder $payments) use ($method) {
                    if ($payments->getConnection()->getDriverName() === 'sqlite') {
                        $payments->whereRaw("EXISTS (SELECT 1 FROM json_each(orders.payments) WHERE json_extract(value, '$.method') = ?)", [$method]);
                    } else {
                        $payments->whereJsonContains('payments', [['method' => $method]]);
                    }
                });
            });
        }

        return $query;
    }

    /** Amount retained per method, in minor units; null means payment was not recorded. */
    public function retainedPayments(Order $order): ?array
    {
        $payments = $order->payments;
        if (! $payments) {
            if ($order->amount_paid === null) {
                return null;
            }
            // Older orders recorded only one method and may include overpayment.
            $payments = [[
                'method' => $order->payment_method,
                'amount' => min((float) $order->amount_paid, (float) $order->total_amount),
            ]];
        }

        $totals = [];
        foreach ($payments as $payment) {
            $method = in_array($payment['method'], self::METHODS, true) ? $payment['method'] : 'other';
            $totals[$method] = ($totals[$method] ?? 0) + (int) round((float) $payment['amount'] * 100);
        }
        $change = max(0, array_sum($totals) - (int) round((float) $order->total_amount * 100));
        if (isset($totals['cash'])) {
            $totals['cash'] = max(0, $totals['cash'] - $change);
        }

        return $totals;
    }

    public function summarize(Builder $filteredQuery): array
    {
        $totals = array_fill_keys([...self::METHODS, 'other'], 0);
        $completedOrders = 0;
        $unrecordedOrders = 0;

        foreach ((clone $filteredQuery)->where('status', 'completed')
            ->select(['id', 'total_amount', 'amount_paid', 'payment_method', 'payments'])->lazyById(500) as $order) {
            $completedOrders++;
            $payments = $this->retainedPayments($order);
            if ($payments === null) {
                $unrecordedOrders++;

                continue;
            }
            foreach ($payments as $method => $amount) {
                $totals[$method] += $amount;
            }
        }

        return [
            'completed_orders' => $completedOrders,
            'unrecorded_orders' => $unrecordedOrders,
            'total_received' => array_sum($totals) / 100,
            'by_method' => array_map(fn ($amount) => $amount / 100, $totals),
        ];
    }
}
