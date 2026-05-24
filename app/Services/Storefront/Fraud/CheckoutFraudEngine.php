<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud;

use App\Models\CheckoutSession;
use App\Services\Storefront\Fraud\Contracts\CheckoutFraudRule;
use App\Services\Storefront\Fraud\Data\CheckoutFraudVerdict;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Runs every configured CheckoutFraudRule. The engine combines all
 * verdicts and returns the strongest:
 *
 *   - any rule says `deny`  → final outcome is `deny`
 *   - else any rule says `warn` → final outcome is `warn`
 *   - else `allow`
 *
 * `flags` from every rule are merged so the dashboard sees the full
 * picture. Severity is the max across all rules' verdicts.
 *
 * Same shape as the scanner-side `FraudEngine` — one mental model.
 */
class CheckoutFraudEngine
{
    public function __construct(protected Container $container) {}

    public function evaluate(CheckoutSession $session, Request $request): CheckoutFraudVerdict
    {
        $rules = (array) config('storefront.fraud_rules', []);
        if (empty($rules)) {
            return CheckoutFraudVerdict::allow();
        }

        $outcome = CheckoutFraudVerdict::OUTCOME_ALLOW;
        $reasonCode = null;
        $severity = 0;
        $flags = [];

        foreach ($rules as $ruleClass) {
            if (! is_string($ruleClass) || ! class_exists($ruleClass)) {
                continue;
            }
            $rule = $this->container->make($ruleClass);
            if (! $rule instanceof CheckoutFraudRule) {
                continue;
            }

            $verdict = $rule->evaluate($session, $request);
            $flags = array_values(array_unique(array_merge($flags, $verdict->flags)));

            if ($verdict->severity > $severity) {
                $severity = $verdict->severity;
            }

            if ($verdict->outcome === CheckoutFraudVerdict::OUTCOME_DENY) {
                $outcome = CheckoutFraudVerdict::OUTCOME_DENY;
                $reasonCode = $verdict->reasonCode;
            } elseif (
                $verdict->outcome === CheckoutFraudVerdict::OUTCOME_WARN
                && $outcome !== CheckoutFraudVerdict::OUTCOME_DENY
            ) {
                $outcome = CheckoutFraudVerdict::OUTCOME_WARN;
                $reasonCode ??= $verdict->reasonCode;
            }
        }

        return new CheckoutFraudVerdict($outcome, $reasonCode, $severity, $flags);
    }
}
