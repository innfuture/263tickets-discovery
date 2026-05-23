<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical sandbox lifecycle (design spec §3.1). Every emulated
 * provider's status strings map onto these — the kernel keeps a single
 * state machine while drivers translate to/from provider vocab.
 *
 * Terminal: VOIDED, REFUNDED, FAILED, DISPUTE_WON, DISPUTE_LOST.
 */
enum SandboxState: string
{
    case INITIATED = 'initiated';
    case AUTH_PENDING = 'auth_pending';
    case AUTHORIZED = 'authorized';
    case CAPTURED = 'captured';
    case PART_REFUNDED = 'part_refunded';
    case REFUNDED = 'refunded';
    case VOIDED = 'voided';
    case FAILED = 'failed';
    case DISPUTED = 'disputed';
    case DISPUTE_WON = 'dispute_won';
    case DISPUTE_LOST = 'dispute_lost';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::VOIDED, self::REFUNDED, self::FAILED,
            self::DISPUTE_WON, self::DISPUTE_LOST => true,
            default => false,
        };
    }

    /**
     * Lower-case label suitable for the public PaymentStatus mapping
     * the SandboxGateway driver exposes to the host application.
     */
    public function toPaymentStatus(): PaymentStatus
    {
        return match ($this) {
            self::INITIATED, self::AUTH_PENDING => PaymentStatus::PENDING,
            self::AUTHORIZED => PaymentStatus::AUTHORIZED,
            self::CAPTURED => PaymentStatus::CAPTURED,
            self::PART_REFUNDED => PaymentStatus::PARTIALLY_REFUNDED,
            self::REFUNDED => PaymentStatus::REFUNDED,
            self::VOIDED => PaymentStatus::CANCELLED,
            self::FAILED => PaymentStatus::FAILED,
            self::DISPUTED, self::DISPUTE_LOST => PaymentStatus::DISPUTED,
            self::DISPUTE_WON => PaymentStatus::CAPTURED,
        };
    }
}
