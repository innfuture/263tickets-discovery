<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\SandboxScenario;
use App\Enums\SandboxState;
use App\Services\Payments\Data\ChargeRequest;

/**
 * Maps a (request + scenario) tuple onto a concrete Outcome. Two
 * inputs:
 *   1. Explicit `scenario` from caller metadata.
 *   2. Magic value lookup on the payment instrument — caller using
 *      `4000 0000 0000 9995` automatically resolves to DECLINE_INSUFFICIENT.
 *
 * Explicit scenarios win when both are present, so a developer can
 * force a behavior that does not match the magic value.
 *
 * Adding a scenario: add it to the SandboxScenario enum AND a branch
 * here. The two are intentionally a single match() so adding one
 * without the other is a compile-time error.
 */
class ScenarioResolver
{
    public function __construct(protected MagicValues $magic) {}

    public function resolve(ChargeRequest $request): Outcome
    {
        $scenario = $this->resolveScenario($request);
        $outcome = $this->outcomeFor($scenario, $request);

        // BNPL overlay: a happy-path BNPL charge gets a hosted-checkout
        // redirect even though the base SUCCESS outcome doesn't set one
        // (used for cards). Keep the rest of the outcome intact.
        if (($request->metadata['method'] ?? null) === 'bnpl'
            && $scenario === SandboxScenario::SUCCESS) {
            $outcome = new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::CAPTURED,
                webhookDelaySeconds: 1,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                redirectUrl: $this->bnplUrl($request),
                instructions: 'Complete the BNPL hosted checkout to confirm the loan.',
            );
        }

        return $outcome;
    }

    public function resolveScenario(ChargeRequest $request): SandboxScenario
    {
        $explicit = $request->metadata['scenario'] ?? null;
        if (is_string($explicit) && ($enum = SandboxScenario::tryFrom($explicit)) !== null) {
            return $enum;
        }

        // Method-specific magic-value lookup.
        $method = $request->metadata['method'] ?? 'card';
        $instrument = $request->metadata['pan']
            ?? $request->metadata['instrument']
            ?? null;

        if ($method === 'card' && is_string($instrument)) {
            $card = $this->magic->lookupCard($instrument);
            if ($card !== null) {
                return $card['scenario'];
            }
        }

        if (in_array($method, ['mobile', 'ecocash', 'paynow'], true)) {
            $hit = $this->magic->lookupMsisdn($request->customer->normalisedMsisdn());
            if ($hit !== null) {
                return $hit;
            }
        }

        if ($method === 'ach') {
            $hit = $this->magic->lookupAch((string) ($request->metadata['account_number'] ?? ''));
            if ($hit !== null) {
                return $hit;
            }
        }

        if ($method === 'sepa') {
            $hit = $this->magic->lookupIban((string) ($request->metadata['iban'] ?? ''));
            if ($hit !== null) {
                return $hit;
            }
        }

        return SandboxScenario::SUCCESS;
    }

    public function outcomeFor(SandboxScenario $scenario, ChargeRequest $request): Outcome
    {
        return match ($scenario) {
            SandboxScenario::SUCCESS => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                webhookDelaySeconds: 1,
                webhookEvents: ['payment.authorized', 'payment.captured'],
            ),

            SandboxScenario::DECLINE_INSUFFICIENT => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'insufficient_funds',
                webhookEvents: ['payment.failed'],
            ),
            SandboxScenario::DECLINE_DO_NOT_HONOR => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'do_not_honor',
                webhookEvents: ['payment.failed'],
            ),
            SandboxScenario::DECLINE_LOST_STOLEN => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'lost_card',
                webhookEvents: ['payment.failed', 'charge.flagged'],
            ),
            SandboxScenario::DECLINE_EXPIRED => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'expired_card',
                webhookEvents: ['payment.failed'],
            ),
            SandboxScenario::DECLINE_CVV => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'incorrect_cvc',
                webhookEvents: ['payment.failed'],
            ),
            SandboxScenario::DECLINE_AVS => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                reasonCode: 'avs_mismatch_n',
                webhookEvents: ['payment.authorized', 'payment.captured'],
            ),

            SandboxScenario::FRAUD_HIGH_RISK => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'risk_blocked',
                webhookEvents: ['payment.failed', 'risk.alert'],
            ),
            SandboxScenario::FRAUD_REVIEW => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: null,
                webhookEvents: ['risk.review_open'],
                instructions: 'Held for manual review; capture requires explicit approval.',
            ),

            SandboxScenario::NETWORK_TIMEOUT => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::FAILED,
                webhookDelaySeconds: 30,
                webhookEvents: ['payment.failed'],
                reasonCode: 'processor_timeout',
                responseDelayMs: 5000,
            ),
            SandboxScenario::NETWORK_5XX => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'gateway_unavailable',
                webhookEvents: [],
            ),
            SandboxScenario::NETWORK_INTERMITTENT => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                failNextWebhooks: 2,
            ),

            SandboxScenario::WEBHOOK_OUT_OF_ORDER => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                outOfOrder: true,
            ),
            SandboxScenario::WEBHOOK_DUPLICATE => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                duplicateCount: 3,
            ),
            SandboxScenario::WEBHOOK_LATE => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::CAPTURED,
                webhookDelaySeconds: 600,
                webhookEvents: ['payment.captured'],
            ),

            SandboxScenario::THREE_DS_FRICTIONLESS => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: SandboxState::CAPTURED,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                instructions: '3DS frictionless — liability shifted.',
            ),
            SandboxScenario::THREE_DS_CHALLENGE_PASSED => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::CAPTURED,
                webhookDelaySeconds: 5,
                webhookEvents: ['payment.authorized', 'payment.captured'],
                redirectUrl: $this->threeDsUrl($request, accept: true),
                instructions: 'Complete the 3DS challenge to confirm the payment.',
            ),
            SandboxScenario::THREE_DS_CHALLENGE_FAILED => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::FAILED,
                webhookEvents: ['payment.failed'],
                reasonCode: 'authentication_failed',
                redirectUrl: $this->threeDsUrl($request, accept: false),
            ),

            SandboxScenario::PARTIAL_CAPTURE => new Outcome(
                immediateState: SandboxState::AUTHORIZED,
                followUpState: null,
                webhookEvents: ['payment.authorized'],
                instructions: 'Call capture with an amount < auth to demo partial capture.',
            ),

            SandboxScenario::ACH_R01_NSF,
            SandboxScenario::ACH_R02_CLOSED,
            SandboxScenario::ACH_R29_UNAUTHORIZED => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::FAILED,
                webhookDelaySeconds: 172_800, // T+2 virtual
                webhookEvents: ['payment.failed'],
                reasonCode: match ($scenario) {
                    SandboxScenario::ACH_R01_NSF => 'R01',
                    SandboxScenario::ACH_R02_CLOSED => 'R02',
                    SandboxScenario::ACH_R29_UNAUTHORIZED => 'R29',
                    default => null,
                },
            ),

            SandboxScenario::SEPA_MANDATE_REVOKED => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'MS02',
                webhookEvents: ['payment.failed'],
            ),

            SandboxScenario::BNPL_DECLINED => new Outcome(
                immediateState: SandboxState::FAILED,
                reasonCode: 'bnpl_credit_denied',
                webhookEvents: ['loan.denied'],
            ),
            SandboxScenario::BNPL_INSTALLMENT_LATE => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::CAPTURED,
                webhookEvents: ['payment.authorized', 'payment.captured', 'customer.late'],
                webhookDelaySeconds: 30,
                redirectUrl: $this->bnplUrl($request),
                instructions: 'Complete the BNPL hosted checkout to confirm the loan.',
            ),

            SandboxScenario::MOBILE_USER_CANCEL => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::VOIDED,
                webhookDelaySeconds: 8,
                webhookEvents: ['payment.cancelled'],
                reasonCode: 'cancelled_by_payer',
                instructions: 'Payer is being prompted on their phone.',
            ),
            SandboxScenario::MOBILE_NO_RESPONSE => new Outcome(
                immediateState: SandboxState::AUTH_PENDING,
                followUpState: SandboxState::FAILED,
                webhookDelaySeconds: 90,
                webhookEvents: ['payment.failed'],
                reasonCode: 'no_response',
            ),
        };
    }

    protected function threeDsUrl(ChargeRequest $request, bool $accept): string
    {
        return rtrim((string) config('app.url'), '/')
            .'/sandbox/payments/three-ds/acs?ref='.rawurlencode($request->reference)
            .'&accept='.($accept ? '1' : '0');
    }

    /**
     * Build the BNPL hosted-checkout URL for the provider named in
     * metadata (defaults to klarna). The page reads `ref` to look up
     * the transaction.
     */
    protected function bnplUrl(ChargeRequest $request): string
    {
        $provider = (string) ($request->metadata['bnpl_provider'] ?? 'klarna');
        if (! in_array($provider, ['klarna', 'afterpay', 'affirm'], true)) {
            $provider = 'klarna';
        }

        return rtrim((string) config('app.url'), '/')
            .'/sandbox/payments/bnpl/'.$provider.'?ref='.rawurlencode($request->reference);
    }
}
