<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Models\SandboxMerchant;
use App\Models\SandboxPaymentMethod;
use App\Services\Payments\Data\ChargeRequest;

/**
 * Card-on-file / off-session support (§5.1). Two responsibilities:
 *
 *   1. Save a card after first auth so the host app can reference it
 *      later via `payment_method_token`.
 *   2. Resolve a `payment_method_token` in a subsequent ChargeRequest's
 *      metadata back into a PAN-shaped lookup so the ScenarioResolver
 *      can still magic-value-match the saved card.
 *
 * Off-session rule: a charge against a saved method without a
 * `network_transaction_id` (issued on the first auth) is rejected with
 * `authentication_required` — mirrors real-world SCA.
 */
class SavedMethods
{
    public function __construct(protected MagicValues $magic) {}

    public function save(
        SandboxMerchant $merchant,
        string $pan,
        ?string $email = null,
        ?int $expMonth = null,
        ?int $expYear = null,
    ): SandboxPaymentMethod {
        $digits = preg_replace('/\D+/', '', $pan) ?? '';
        $card = $this->magic->lookupCard($pan);

        return SandboxPaymentMethod::create([
            'sandbox_merchant_id' => $merchant->id,
            'kind' => 'card',
            'brand' => $card['brand'] ?? null,
            'last4' => substr($digits, -4),
            'exp_month' => $expMonth,
            'exp_year' => $expYear,
            'country' => $card['country'] ?? null,
            'fingerprint' => hash('sha256', $digits),
            'customer_email' => $email,
            // Network transaction ID populated on first successful auth.
            'network_transaction_id' => null,
            'metadata' => ['original_pan_hash' => substr(hash('sha256', $digits), 0, 16)],
        ]);
    }

    /**
     * After a first successful AUTHORIZED charge, stamp the saved
     * method with a synthetic network_transaction_id so future
     * off-session calls are allowed.
     */
    public function markAuthenticated(SandboxPaymentMethod $method): void
    {
        if ($method->network_transaction_id !== null) {
            return;
        }
        $method->update([
            'network_transaction_id' => 'mit_'.bin2hex(random_bytes(12)),
            'last_used_at' => now(),
        ]);
    }

    public function revoke(SandboxPaymentMethod $method): void
    {
        $method->update(['revoked_at' => now()]);
    }

    /**
     * If the incoming charge references a saved method, translate it
     * back to a `pan` + scenario hint in metadata so the rest of the
     * sandbox treats it as if the caller had supplied the PAN.
     *
     * Returns the resolved SandboxPaymentMethod (or null) so the
     * kernel can stamp `network_transaction_id` on first successful
     * auth.
     */
    public function hydrate(ChargeRequest $request): ?SandboxPaymentMethod
    {
        $token = $request->metadata['payment_method_token'] ?? null;
        if (! is_string($token) || $token === '') {
            return null;
        }

        $method = SandboxPaymentMethod::query()->where('token', $token)->first();
        if ($method === null || $method->revoked_at !== null) {
            return null;
        }

        return $method;
    }

    /**
     * Off-session rule check. Returns an error code string if the
     * charge should be rejected, null if allowed.
     */
    public function offSessionRejectionFor(ChargeRequest $request, ?SandboxPaymentMethod $method): ?string
    {
        $isOffSession = (bool) ($request->metadata['off_session'] ?? false);
        if (! $isOffSession || $method === null) {
            return null;
        }
        if ($method->network_transaction_id === null) {
            return 'authentication_required';
        }
        if ($method->revoked_at !== null) {
            return 'payment_method_revoked';
        }

        return null;
    }
}
