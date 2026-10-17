<?php

namespace App\Support;

class PosPrice
{
    public static function orderTotal(float $subtotal, float $discountPercentage): float
    {
        $discount = round($subtotal * $discountPercentage / 100, 2);
        $total = round($subtotal - $discount, 2);

        return round($total, -3, PHP_ROUND_HALF_UP);
    }

    public static function inMmk(float $price, ?string $currency, array $settings): float
    {
        if (! $currency || $currency === 'MMK') {
            return $price;
        }

        $rate = (float) ($settings[strtolower($currency).'_rate'] ?? 1);

        return round($price * $rate, -3, PHP_ROUND_HALF_UP);
    }
}
