<?php

namespace App\Support;

class CustomerPhone
{
    public static function normalize(?string $phone): string
    {
        $phone = preg_replace('/[\s().-]+/u', '', $phone ?? '');
        if (str_starts_with($phone, '00')) {
            $phone = '+'.substr($phone, 2);
        }
        // Myanmar mobile numbers entered with the country code match local 09 numbers.
        if (str_starts_with($phone, '+959')) {
            $phone = '0'.substr($phone, 3);
        }

        return $phone;
    }
}
