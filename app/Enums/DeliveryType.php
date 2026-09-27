<?php

namespace App\Enums;

enum DeliveryType: string
{
    case Courier = 'Courier';
    case ShopPickup = 'Shop Pickup';

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = $type->value;
        }

        return $options;
    }
}
