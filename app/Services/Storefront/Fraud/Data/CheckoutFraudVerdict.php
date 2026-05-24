<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud\Data;

/**
 * Per-rule decision. `outcome` is the verdict; `flags` carry extra
 * detail for the dashboard and any downstream automation.
 */
final class CheckoutFraudVerdict
{
    public const OUTCOME_ALLOW = 'allow';

    public const OUTCOME_WARN = 'warn';

    public const OUTCOME_DENY = 'deny';

    /** @param list<string> $flags */
    public function __construct(
        public readonly string $outcome,
        public readonly ?string $reasonCode = null,
        public readonly int $severity = 0,
        public readonly array $flags = [],
    ) {}

    public static function allow(): self
    {
        return new self(self::OUTCOME_ALLOW);
    }

    /** @param list<string> $flags */
    public static function warn(string $reasonCode, int $severity = 1, array $flags = []): self
    {
        return new self(self::OUTCOME_WARN, $reasonCode, $severity, $flags);
    }

    /** @param list<string> $flags */
    public static function deny(string $reasonCode, int $severity = 5, array $flags = []): self
    {
        return new self(self::OUTCOME_DENY, $reasonCode, $severity, $flags);
    }
}
