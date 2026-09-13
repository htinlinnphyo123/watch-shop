<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class OrderPaymentService
{
    /** Normalize validated tender amounts and compare them in minor currency units. */
    public function summarize(array $payments, float $total): array
    {
        $totalCents = (int) round($total * 100);
        $paidCents = 0;
        $nonCashCents = 0;

        foreach ($payments as $index => &$payment) {
            $cents = (int) round((float) $payment['amount'] * 100);
            if ($cents <= 0 && ! ($totalCents === 0 && count($payments) === 1 && $cents === 0)) {
                throw ValidationException::withMessages(["payments.$index.amount" => 'Enter an amount greater than zero, or remove this payment.']);
            }
            $payment = ['method' => $payment['method'], 'amount' => number_format($cents / 100, 2, '.', '')];
            $paidCents += $cents;
            if ($payment['method'] !== 'cash') {
                $nonCashCents += $cents;
            }
        }
        unset($payment);

        if ($paidCents < $totalCents) {
            throw ValidationException::withMessages(['payments' => 'Payments are '.number_format(($totalCents - $paidCents) / 100, 2).' Ks short of the total due.']);
        }
        if ($nonCashCents > $totalCents) {
            throw ValidationException::withMessages(['payments' => 'Non-cash payments cannot exceed the total due. Change can only be returned from cash.']);
        }

        return [
            'payments' => $payments,
            'payment_method' => count($payments) > 1 ? 'split' : $payments[0]['method'],
            'amount_paid' => $paidCents / 100,
        ];
    }
}
