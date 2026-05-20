<?php

namespace App\Enums;

enum AdPlatform: string
{
    case GoogleAds = 'google_ads';
    case MetaAds = 'meta_ads';
    case YouTube = 'youtube';

    public function label(): string
    {
        return match ($this) {
            self::GoogleAds => 'Google Ads',
            self::MetaAds => 'Meta (Facebook / Instagram)',
            self::YouTube => 'YouTube',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::GoogleAds => 'google',
            self::MetaAds => 'meta',
            self::YouTube => 'youtube',
        };
    }
}
