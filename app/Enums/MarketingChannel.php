<?php

namespace App\Enums;

enum MarketingChannel: string
{
    case WalkIn = 'Walk-in';
    case Facebook = 'Facebook';
    case Instagram = 'Instagram';
    case TikTok = 'TikTok';
    case GoogleSearch = 'Google/Search';
    case Website = 'Website';
    case Referral = 'Referral';
    case Other = 'Other';

    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $channel) {
            $options[$channel->value] = $channel->value;
        }

        return $options;
    }
}
