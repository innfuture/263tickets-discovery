<?php

declare(strict_types=1);

namespace App\Services\Scanning\Data;

/**
 * Outcome of a single FraudRule evaluation.
 *
 *   Allow  — rule had no objection.
 *   Warn   — soft signal; admit but flag for review.
 *   Deny   — block admission.
 *
 * `severity` lets the FraudEngine pick the strongest verdict when
 * many rules speak — higher numbers win.
 */
final class FraudVerdict
{
    public const OUTCOME_ALLOW = 'allow';

    public const OUTCOME_WARN = 'warn';

    public const OUTCOME_DENY = 'deny';

    private function __construct(
        public readonly string $outcome,
        public readonly int $severity,
        public readonly ?string $reasonCode = null,
        public readonly ?string $message = null,
        public readonly string $rule = '',
    ) {}

    public static function allow(string $rule = ''): self
    {
        return new self(self::OUTCOME_ALLOW, 0, rule: $rule);
    }

    public static function warn(string $reasonCode, string $message, int $severity = 1, string $rule = ''): self
    {
        return new self(self::OUTCOME_WARN, max(1, $severity), $reasonCode, $message, $rule);
    }

    public static function deny(string $reasonCode, string $message, int $severity = 5, string $rule = ''): self
    {
        return new self(self::OUTCOME_DENY, max(5, $severity), $reasonCode, $message, $rule);
    }

    public function isDeny(): bool
    {
        return $this->outcome === self::OUTCOME_DENY;
    }

    public function isWarn(): bool
    {
        return $this->outcome === self::OUTCOME_WARN;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'rule' => $this->rule,
            'outcome' => $this->outcome,
            'severity' => $this->severity,
            'reason_code' => $this->reasonCode,
            'message' => $this->message,
        ];
    }
}
