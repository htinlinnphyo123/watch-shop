<?php

namespace App\Support;

use App\Enums\DeliveryStatus;
use App\Enums\DeliveryType;
use App\Enums\MarketingChannel;
use Illuminate\Validation\Rule;

class SalesRecordFields
{
    public const COMMON = ['order_date', 'buying_type', 'delivery_type', 'delivery_status', 'delivery_fees', 'marketing_channel'];
    public const SALES = ['order_type'];
    public const PRE_ORDER = ['model_number', 'price', 'discount_amount', 'delivery_code', 'money_transfer_amount', 'paid_by', 'payment_type', 'deposit_amount'];
    public const MONEY_RULE = 'nullable|numeric|decimal:0,2|min:0|max:9999999999999.99';

    public static function rules(bool $preOrder = false): array
    {
        return [
            'order_date' => 'nullable|date_format:Y-m-d',
            'buying_type' => 'nullable|in:online,in_person',
            'marketing_channel' => ['nullable', Rule::enum(MarketingChannel::class)],
            'delivery_type' => ['nullable', Rule::enum(DeliveryType::class)],
            'delivery_status' => ['nullable', Rule::enum(DeliveryStatus::class)],
            'delivery_fees' => self::MONEY_RULE,
        ] + ($preOrder ? [
            'paid_by' => 'nullable|string|max:255',
            'payment_type' => 'nullable|in:cash,mbanking,cod,foc,other',
            'deposit_amount' => self::MONEY_RULE,
            'model_number' => 'nullable|string|max:255',
            'price' => self::MONEY_RULE,
            'discount_amount' => self::MONEY_RULE,
            'delivery_code' => 'nullable|string|max:255',
            'money_transfer_amount' => self::MONEY_RULE,
        ] : [
            'order_type' => 'nullable|in:instock,preorder',
        ]);
    }
}
