<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentGateway: string
{
    case ECOCASH = 'ecocash';
    case PAYNOW = 'paynow';
    case PESEPAY = 'pesepay';
    case ZIMSWITCH = 'zimswitch';

    public function label(): string
    {
        return match ($this) {
            self::ECOCASH => 'EcoCash',
            self::PAYNOW => 'Paynow',
            self::PESEPAY => 'Pesepay',
            self::ZIMSWITCH => 'Zimswitch',
        };
    }
}
