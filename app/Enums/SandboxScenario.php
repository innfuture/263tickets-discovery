<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catalog of behaviors the sandbox can produce on demand. Each scenario
 * is a deterministic state path — the ScenarioResolver turns one of
 * these into a series of state transitions + webhook deliveries.
 *
 * Keep this list aligned with the table in design spec §3.3. New
 * scenarios go in the resolver's match() block and here together.
 */
enum SandboxScenario: string
{
    case SUCCESS = 'success';

    // Card declines
    case DECLINE_INSUFFICIENT = 'decline.insufficient';
    case DECLINE_DO_NOT_HONOR = 'decline.do_not_honor';
    case DECLINE_LOST_STOLEN = 'decline.lost_stolen';
    case DECLINE_EXPIRED = 'decline.expired';
    case DECLINE_CVV = 'decline.cvv';
    case DECLINE_AVS = 'decline.avs';

    // Risk / fraud
    case FRAUD_HIGH_RISK = 'fraud.high_risk';
    case FRAUD_REVIEW = 'fraud.review';

    // Network conditions
    case NETWORK_TIMEOUT = 'network.timeout';
    case NETWORK_5XX = 'network.5xx';
    case NETWORK_INTERMITTENT = 'network.intermittent';

    // Webhook quirks
    case WEBHOOK_OUT_OF_ORDER = 'webhook.out_of_order';
    case WEBHOOK_DUPLICATE = 'webhook.duplicate';
    case WEBHOOK_LATE = 'webhook.late';

    // 3DS
    case THREE_DS_FRICTIONLESS = '3ds.frictionless';
    case THREE_DS_CHALLENGE_PASSED = '3ds.challenge_passed';
    case THREE_DS_CHALLENGE_FAILED = '3ds.challenge_failed';

    // Partial capture
    case PARTIAL_CAPTURE = 'partial_capture';

    // ACH returns
    case ACH_R01_NSF = 'ach.return.r01';
    case ACH_R02_CLOSED = 'ach.return.r02';
    case ACH_R29_UNAUTHORIZED = 'ach.return.r29';

    // SEPA
    case SEPA_MANDATE_REVOKED = 'sepa.mandate.revoked';

    // BNPL
    case BNPL_DECLINED = 'bnpl.declined';
    case BNPL_INSTALLMENT_LATE = 'bnpl.installment_late';

    // Mobile money
    case MOBILE_USER_CANCEL = 'mobile.user_cancel';
    case MOBILE_NO_RESPONSE = 'mobile.no_response';

    /**
     * Scenarios whose terminal state is FAILED (no funds movement).
     */
    public function failsImmediately(): bool
    {
        return match ($this) {
            self::DECLINE_INSUFFICIENT,
            self::DECLINE_DO_NOT_HONOR,
            self::DECLINE_LOST_STOLEN,
            self::DECLINE_EXPIRED,
            self::DECLINE_CVV,
            self::FRAUD_HIGH_RISK,
            self::THREE_DS_CHALLENGE_FAILED,
            self::BNPL_DECLINED => true,
            default => false,
        };
    }

    /**
     * Returns true for scenarios that require an asynchronous follow-up
     * (settlement delay, manual approval, etc.) before reaching a
     * terminal state — they exit charge() in AUTH_PENDING.
     */
    public function isAsynchronous(): bool
    {
        return match ($this) {
            self::NETWORK_TIMEOUT,
            self::THREE_DS_CHALLENGE_PASSED,
            self::ACH_R01_NSF,
            self::ACH_R02_CLOSED,
            self::ACH_R29_UNAUTHORIZED,
            self::SEPA_MANDATE_REVOKED,
            self::FRAUD_REVIEW,
            self::MOBILE_USER_CANCEL,
            self::MOBILE_NO_RESPONSE => true,
            default => false,
        };
    }
}
