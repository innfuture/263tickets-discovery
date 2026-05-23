<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

use InvalidArgumentException;

/**
 * Money is stored in minor units (cents) to avoid float drift. Drivers
 * convert to / from the major-unit string each gateway expects.
 *
 * Currencies are accepted as three-letter ISO codes plus "ZiG", which
 * EcoCash exposes for the new Zimbabwean currency (the numeric code is
 * 932 but the gateway accepts the literal string too).
 */
final class Money
{
    public function __construct(
        public readonly int $amountMinor,
        public readonly string $currency,
    ) {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }

        if ($currency === '' || strlen($currency) > 6) {
            throw new InvalidArgumentException('Currency must be a 3–6 char code.');
        }
    }

    public static function ofMajor(string|float|int $major, string $currency): self
    {
        $clean = is_string($major) ? trim($major) : (string) $major;
        if (! is_numeric($clean)) {
            throw new InvalidArgumentException("Non-numeric major amount: {$clean}");
        }

        // Two-decimal currencies cover all four gateways we support today.
        $minor = (int) round(((float) $clean) * 100);

        return new self($minor, strtoupper($currency) === 'ZIG' ? 'ZiG' : strtoupper($currency));
    }

    public function major(): string
    {
        return number_format($this->amountMinor / 100, 2, '.', '');
    }

    public function isZero(): bool
    {
        return $this->amountMinor === 0;
    }

    public function withCurrency(string $currency): self
    {
        return new self($this->amountMinor, $currency);
    }

    public function equals(self $other): bool
    {
        return $this->amountMinor === $other->amountMinor
            && $this->currency === $other->currency;
    }
}
