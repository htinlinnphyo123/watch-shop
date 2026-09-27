<?php

namespace App\Support;

class PosPrice
{
    public static function inMmk(float $price, ?string $currency, array $settings): float
    {
        if (! $currency || $currency === 'MMK') {
            return $price;
        }

        $rate = (float) ($settings[strtolower($currency).'_rate'] ?? 1);

        return round($price * $rate, -3, PHP_ROUND_HALF_UP);
    }
}
