<?php

namespace App\Enums;

enum ExpenseCategory: string
{
    case Bill = 'bill';
    case Service = 'service';
    case Shop = 'shop';
    case Delivery = 'delivery';

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $category) {
            $options[$category->value] = $category->name;
        }

        return $options;
    }
}
