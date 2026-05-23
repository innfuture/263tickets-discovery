<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Normalised payment lifecycle. Drivers map provider-specific statuses
 * (Paynow's "Paid"/"Awaiting Delivery", Pesepay's "INITIATED"/"COMPLETED",
 * EcoCash's "Charged", Zimswitch's `result.code` patterns) into one of
 * these values so callers never branch on provider strings.
 */
enum PaymentStatus: string
{
    case PENDING = 'pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
    case REVERSED = 'reversed';
    case DISPUTED = 'disputed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::CAPTURED,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::REVERSED => true,
            default => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::CAPTURED || $this === self::AUTHORIZED;
    }
}
