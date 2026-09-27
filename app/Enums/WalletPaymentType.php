<?php

namespace App\Enums;

enum WalletPaymentType: string
{
    case Cash = 'cash';
    case KbzPay = 'kbz_pay';
    case Card = 'card';
    case CbPay = 'cb_pay';
    case AyaPay = 'aya_pay';
    case Other = 'other';

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $type) {
            $options[$type->value] = match ($type) {
                self::KbzPay => 'KBZ Pay',
                self::CbPay => 'CB Pay',
                self::AyaPay => 'Aya Pay',
                default => $type->name,
            };
        }

        return $options;
    }
}
