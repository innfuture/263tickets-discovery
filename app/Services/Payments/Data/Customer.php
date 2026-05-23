<?php

declare(strict_types=1);

namespace App\Services\Payments\Data;

/**
 * Payer-side metadata the gateways need to attribute the charge. Mobile
 * gateways (EcoCash, Paynow Express) require `msisdn`; card gateways
 * (Zimswitch OPP) prefer `email`. Drivers pull what they need and
 * tolerate the rest being null.
 */
final class Customer
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        /** E.164 without `+`, e.g. 263772222516 — what EcoCash calls endUserId. */
        public readonly ?string $msisdn = null,
        public readonly ?string $ipAddress = null,
    ) {}

    public function normalisedMsisdn(): ?string
    {
        if ($this->msisdn === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $this->msisdn) ?? '';

        // Local Zimbabwean shorthand (07…) → international (2637…).
        if (str_starts_with($digits, '07') && strlen($digits) === 10) {
            $digits = '263'.substr($digits, 1);
        }

        return $digits === '' ? null : $digits;
    }
}
