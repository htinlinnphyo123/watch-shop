<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\OrderAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RoundExistingOrderTotals extends Command
{
    protected $signature = 'orders:round-totals
        {--apply : Save the changes; otherwise preview only}
        {--adjust-paid : Also adjust payments that exactly settled the old total}';

    protected $aliases = ['orders:round-discounted-totals'];

    protected $description = 'Round existing order totals to the nearest 1,000 MMK, with order history';

    public function handle(OrderAuditService $audit): int
    {
        $changed = 0;
        $skipped = 0;
        $this->info($this->option('apply') ? 'Applying order rounding.' : 'Preview only. No orders will be changed.');

        Order::whereIn('status', ['pending', 'completed'])
            ->select('id')->chunkById(200, function ($orders) use ($audit, &$changed, &$skipped) {
                foreach ($orders as $candidate) {
                    DB::transaction(function () use ($candidate, $audit, &$changed, &$skipped) {
                        $order = Order::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
                        if (! in_array($order->status, ['pending', 'completed'], true)) {
                            return;
                        }

                        $oldCents = (int) round((float) $order->total_amount * 100);
                        $newCents = (int) (round($oldCents / 100000, 0, PHP_ROUND_HALF_UP) * 100000);
                        if ($oldCents === $newCents) {
                            return;
                        }

                        $changes = ['total_amount' => $newCents / 100];
                        if ($this->option('adjust-paid') && $order->amount_paid !== null
                            && (int) round((float) $order->amount_paid * 100) === $oldCents) {
                            $payments = $order->payments;
                            if ($payments && ! is_array($payments)) {
                                $this->warn("Skipped order #{$order->id}: invalid payment details.");
                                $skipped++;

                                return;
                            }
                            if ($payments) {
                                $paymentCents = [];
                                foreach ($payments as $index => $payment) {
                                    if (! is_array($payment) || ! isset($payment['method'], $payment['amount'])
                                        || ! is_numeric($payment['amount']) || (float) $payment['amount'] < 0) {
                                        $this->warn("Skipped order #{$order->id}: invalid payment details.");
                                        $skipped++;

                                        return;
                                    }
                                    $paymentCents[$index] = (int) round((float) $payment['amount'] * 100);
                                }
                                if (array_sum($paymentCents) !== $oldCents) {
                                    $this->warn("Skipped order #{$order->id}: payment breakdown does not match amount paid.");
                                    $skipped++;

                                    return;
                                }

                                // Prefer cash; otherwise use the largest payment so the adjustment
                                // never makes a small split payment negative.
                                arsort($paymentCents);
                                $target = array_key_first($paymentCents);
                                foreach ($paymentCents as $index => $amount) {
                                    if ($payments[$index]['method'] === 'cash' && $amount + $newCents - $oldCents >= 0) {
                                        $target = $index;
                                        break;
                                    }
                                }
                                $adjusted = $paymentCents[$target] + $newCents - $oldCents;
                                if ($adjusted < 0) {
                                    $this->warn("Skipped order #{$order->id}: split payments need manual review.");
                                    $skipped++;

                                    return;
                                }
                                $payments[$target]['amount'] = number_format($adjusted / 100, 2, '.', '');
                                $changes['payments'] = array_values($payments);
                            }
                            $changes['amount_paid'] = $newCents / 100;
                        }

                        $paymentNote = array_key_exists('amount_paid', $changes) ? '; matching payment adjusted' : '; recorded payment unchanged';
                        $this->line("Order #{$order->id}: ".number_format($oldCents / 100, 2).' → '.number_format($newCents / 100, 2).$paymentNote);
                        if ($this->option('apply')) {
                            $before = $audit->before($order);
                            $order->fill($changes);
                            $order->edit_version++;
                            // Retain the original order date and last business edit timestamp.
                            $order->timestamps = false;
                            $order->save();
                            $audit->record($order, 'total_rounded', $before);
                        }
                        $changed++;
                    });
                }
            });

        $this->info(($this->option('apply') ? 'Updated' : 'Would update')." {$changed} order(s); skipped {$skipped} for review.");

        return $skipped ? self::FAILURE : self::SUCCESS;
    }
}
