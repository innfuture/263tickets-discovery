<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\ChargeResult;

/**
 * The base contract every driver implements. Capability contracts
 * (RefundsTransactions, PollsTransactionStatus, HandlesWebhooks) extend
 * the surface for providers that support those flows — callers check
 * `instanceof` before invoking.
 */
interface PaymentGateway
{
    /**
     * Short machine identifier — matches the config/payments.php key
     * (`paynow`, `ecocash`, …) and the URL path segment for webhooks.
     */
    public function identifier(): string;

    /**
     * Human label suitable for UI surfaces.
     */
    public function label(): string;

    /**
     * Currencies the gateway will accept in upper-case ISO-4217
     * (plus the literal "ZiG" for the EcoCash ZiG wallet).
     *
     * @return array<int, string>
     */
    public function supportedCurrencies(): array;

    /**
     * Initiate a payment. Most providers return PENDING and settle via
     * webhook; the result still carries enough to redirect the payer
     * (`redirectUrl`) or poll later (`pollUrl`).
     */
    public function charge(ChargeRequest $request): ChargeResult;
}
