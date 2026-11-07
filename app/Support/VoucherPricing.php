<?php

namespace App\Support;

class VoucherPricing
{
    /** Allocate in cents per physical watch, independent of cart grouping. */
    public static function calculate(array $lines, float $percentage): array
    {
        $units = [];
        $owners = [];
        foreach ($lines as $index => $line) {
            $price = (float) $line['price'];
            $net = max(0, (int) round(($price - round($price * (float) $line['discount_percentage'] / 100, 2)) * 100));
            for ($i = 0; $i < $line['quantity']; $i++) {
                $units[] = $net;
                $owners[] = $index;
            }
        }
        $discount = (int) round(array_sum($units) * $percentage / 100);
        $shares = self::allocate($discount, $units);
        $nets = array_map(fn ($value, $key) => $value - $shares[$key], $units, array_keys($units));
        $total = (int) round(array_sum($nets) / 100000, 0, PHP_ROUND_HALF_UP) * 100000;
        $rounding = $total - array_sum($nets);
        $adjustments = array_fill(0, count($units), 0);
        if ($rounding < 0) {
            $adjustments = array_map(fn ($value) => -$value, self::allocate(-$rounding, $nets));
        } elseif ($rounding > 0) {
            $adjustments = self::allocate($rounding, array_map(fn ($net) => $net > 0 ? $rounding : 0, $nets));
        }
        $allocations = array_fill(0, count($lines), ['overall_discount_amount' => 0, 'rounding_adjustment' => 0, 'net_total' => 0]);
        foreach ($owners as $key => $owner) {
            $allocations[$owner]['overall_discount_amount'] += $shares[$key];
            $allocations[$owner]['rounding_adjustment'] += $adjustments[$key];
            $allocations[$owner]['net_total'] += $nets[$key] + $adjustments[$key];
        }
        foreach ($allocations as &$allocation) {
            $allocation = array_map(fn ($cents) => $cents / 100, $allocation);
        }
        return ['discount' => $discount / 100, 'total' => $total / 100, 'lines' => $allocations];
    }

    /** Reconstruct per-unit export values from a saved line, including cent remainders. */
    public static function units(float $price, int $quantity, float $percentage, float $discount, float $rounding): array
    {
        $net = max(0, (int) round(($price - round($price * $percentage / 100, 2)) * 100));
        $shares = self::allocate((int) round($discount * 100), array_fill(0, $quantity, $net));
        $nets = array_map(fn ($share) => $net - $share, $shares);
        $cents = (int) round(abs($rounding) * 100);
        $adjustments = self::allocate($cents, $rounding < 0 ? $nets : array_map(fn ($value) => $value > 0 ? $cents : 0, $nets));
        return array_map(fn ($key) => [
            'Overall Discount' => $shares[$key] / 100,
            'Rounding Adjustment' => ($rounding < 0 ? -$adjustments[$key] : $adjustments[$key]) / 100,
            'Net Sales' => ($nets[$key] + ($rounding < 0 ? -$adjustments[$key] : $adjustments[$key])) / 100,
        ], array_keys($nets));
    }

    /** Equal shares, capped at each watch's remaining value; remainder goes in stable row order. */
    private static function allocate(int $amount, array $caps): array
    {
        $shares = array_fill(0, count($caps), 0);
        while ($amount > 0) {
            $active = array_keys(array_filter($caps, fn ($cap, $key) => $cap > $shares[$key], ARRAY_FILTER_USE_BOTH));
            if (! $active) break;
            $each = intdiv($amount, count($active));
            $remainder = $amount % count($active);
            foreach ($active as $position => $key) {
                $share = min($caps[$key] - $shares[$key], $each + ($position < $remainder ? 1 : 0));
                $shares[$key] += $share;
                $amount -= $share;
            }
        }
        return $shares;
    }
}
