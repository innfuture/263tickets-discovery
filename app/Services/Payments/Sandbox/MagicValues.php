<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Enums\SandboxScenario;

/**
 * Registry of magic test values (design spec §4.5). When the caller
 * supplies one of these — a card PAN, MSISDN, IBAN, ACH routing pair —
 * the ScenarioResolver picks the matching scenario automatically.
 *
 * Mirrors Stripe's well-known conventions where possible so engineers
 * already familiar with `4242 4242 4242 4242` get immediate intuition.
 *
 * The catalog is data-only — no behavior here. Behaviors live in the
 * scenario the value maps to.
 */
class MagicValues
{
    /**
     * Cards keyed by PAN (digits-only, spaces stripped). Lookup is
     * exact-match; partial matching would risk colliding with caller-
     * supplied inputs.
     *
     * @return array<string, array{scenario: SandboxScenario, brand: string, country: string}>
     */
    public function cards(): array
    {
        return [
            '4242424242424242' => ['scenario' => SandboxScenario::SUCCESS, 'brand' => 'visa', 'country' => 'US'],
            '4000000000000002' => ['scenario' => SandboxScenario::DECLINE_DO_NOT_HONOR, 'brand' => 'visa', 'country' => 'US'],
            '4000000000009995' => ['scenario' => SandboxScenario::DECLINE_INSUFFICIENT, 'brand' => 'visa', 'country' => 'US'],
            '4000000000009987' => ['scenario' => SandboxScenario::DECLINE_LOST_STOLEN, 'brand' => 'visa', 'country' => 'US'],
            '4000000000000069' => ['scenario' => SandboxScenario::DECLINE_EXPIRED, 'brand' => 'visa', 'country' => 'US'],
            '4000000000000127' => ['scenario' => SandboxScenario::DECLINE_CVV, 'brand' => 'visa', 'country' => 'US'],
            '4000000000004954' => ['scenario' => SandboxScenario::DECLINE_AVS, 'brand' => 'visa', 'country' => 'US'],
            '4000000000000259' => ['scenario' => SandboxScenario::FRAUD_HIGH_RISK, 'brand' => 'visa', 'country' => 'US'],
            '4000000000009235' => ['scenario' => SandboxScenario::FRAUD_REVIEW, 'brand' => 'visa', 'country' => 'US'],
            '4000002760003184' => ['scenario' => SandboxScenario::THREE_DS_FRICTIONLESS, 'brand' => 'visa', 'country' => 'US'],
            '4000000000003220' => ['scenario' => SandboxScenario::THREE_DS_CHALLENGE_PASSED, 'brand' => 'visa', 'country' => 'US'],
            '4000000000003055' => ['scenario' => SandboxScenario::THREE_DS_CHALLENGE_FAILED, 'brand' => 'visa', 'country' => 'US'],
            '5555555555554444' => ['scenario' => SandboxScenario::SUCCESS, 'brand' => 'mastercard', 'country' => 'US'],
            '378282246310005' => ['scenario' => SandboxScenario::SUCCESS, 'brand' => 'amex', 'country' => 'US'],
            '6011111111111117' => ['scenario' => SandboxScenario::SUCCESS, 'brand' => 'discover', 'country' => 'US'],
        ];
    }

    /**
     * MSISDNs for mobile-money emulations (EcoCash / Paynow / Pesepay).
     * Suffix-based — the ScenarioResolver matches on the last 4 digits
     * to keep the catalog short.
     *
     * @return array<string, SandboxScenario>
     */
    public function msisdnSuffixes(): array
    {
        return [
            '2516' => SandboxScenario::SUCCESS,
            '9999' => SandboxScenario::MOBILE_USER_CANCEL,
            '8888' => SandboxScenario::MOBILE_NO_RESPONSE,
            '7777' => SandboxScenario::DECLINE_INSUFFICIENT,
        ];
    }

    /**
     * ACH account suffixes (last 9 digits of account number).
     *
     * @return array<string, SandboxScenario>
     */
    public function achAccountSuffixes(): array
    {
        return [
            '000123456789' => SandboxScenario::SUCCESS,
            '000111111111' => SandboxScenario::ACH_R01_NSF,
            '000222222222' => SandboxScenario::ACH_R02_CLOSED,
            '000333333333' => SandboxScenario::ACH_R29_UNAUTHORIZED,
        ];
    }

    /**
     * @return array<string, SandboxScenario>
     */
    public function ibans(): array
    {
        return [
            'DE89370400440532013000' => SandboxScenario::SUCCESS,
            'DE89370400440532013111' => SandboxScenario::SEPA_MANDATE_REVOKED,
        ];
    }

    public function lookupCard(?string $pan): ?array
    {
        if ($pan === null) {
            return null;
        }
        $clean = preg_replace('/\D+/', '', $pan) ?? '';

        return $this->cards()[$clean] ?? null;
    }

    public function lookupMsisdn(?string $msisdn): ?SandboxScenario
    {
        if ($msisdn === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $msisdn) ?? '';
        if ($digits === '') {
            return null;
        }

        // PHP coerces numeric-string array keys to ints; cast back so
        // str_ends_with's strict string typing is satisfied.
        foreach ($this->msisdnSuffixes() as $suffix => $scenario) {
            if (str_ends_with($digits, (string) $suffix)) {
                return $scenario;
            }
        }

        return null;
    }

    public function lookupAch(?string $accountNumber): ?SandboxScenario
    {
        if ($accountNumber === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $accountNumber) ?? '';

        // PHP coerces numeric-string array keys to ints — normalise both sides.
        foreach ($this->achAccountSuffixes() as $candidate => $scenario) {
            if ((string) $candidate === $digits) {
                return $scenario;
            }
        }

        return null;
    }

    public function lookupIban(?string $iban): ?SandboxScenario
    {
        if ($iban === null) {
            return null;
        }
        $clean = strtoupper(str_replace(' ', '', $iban));

        return $this->ibans()[$clean] ?? null;
    }
}
