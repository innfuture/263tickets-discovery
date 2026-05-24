<?php

declare(strict_types=1);

namespace App\Enums;

enum WaitlistStatus: string
{
    case Pending = 'pending';
    case Notified = 'notified';
    case Converted = 'converted';
    case Expired = 'expired';
}
