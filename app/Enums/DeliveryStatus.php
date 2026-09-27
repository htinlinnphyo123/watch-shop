<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Pending = 'Pending';
    case Preparing = 'Preparing';
    case Dispatched = 'Dispatched';
    case Delivered = 'Delivered';
    case Returned = 'Returned';
    case Cancelled = 'Cancelled';

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $status) {
            $options[$status->value] = $status->value;
        }

        return $options;
    }
}
