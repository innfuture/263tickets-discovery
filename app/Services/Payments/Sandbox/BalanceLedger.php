<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Models\SandboxBalanceEntry;
use App\Models\SandboxDispute;
use App\Models\SandboxMerchant;
use App\Models\SandboxRefund;
use App\Models\SandboxTransaction;
use Carbon\CarbonImmutable;

/**
 * Per-merchant balance ledger (§6, spec phase 4). Every capture credits
 * the merchant; refunds and chargebacks debit. Card captures land
 * immediately-available; ACH captures hold T+2; SEPA T+1.
 *
 * Callers ask for `balance($merchant)` to get a three-tuple of:
 *   pending     — committed but not yet `available_at`
 *   available   — `available_at` ≤ virtual-now
 *   total       — pending + available
 */
class BalanceLedger
{
    public function __construct(protected SandboxClock $clock) {}

    public function recordCapture(SandboxTransaction $transaction, ?int $amountMinor = null): SandboxBalanceEntry
    {
        $amount = $amountMinor ?? (int) $transaction->amount_captured_minor ?: (int) $transaction->amount_minor;

        return SandboxBalanceEntry::create([
            'sandbox_merchant_id' => $transaction->sandbox_merchant_id,
            'sandbox_transaction_id' => $transaction->id,
            'direction' => 'credit',
            'amount_minor' => $amount,
            'currency' => $transaction->currency,
            'category' => 'capture',
            'available_at' => $this->settlementAt($transaction),
            'memo' => "Capture of {$transaction->uuid}",
        ]);
    }

    public function recordRefund(SandboxRefund $refund): SandboxBalanceEntry
    {
        $transaction = $refund->transaction;

        return SandboxBalanceEntry::create([
            'sandbox_merchant_id' => $transaction->sandbox_merchant_id,
            'sandbox_transaction_id' => $transaction->id,
            'sandbox_refund_id' => $refund->id,
            'direction' => 'debit',
            'amount_minor' => $refund->amount_minor,
            'currency' => $refund->currency,
            'category' => 'refund',
            // Refunds debit the merchant immediately upon issuance —
            // available "after" right now so they show up in available
            // balance straight away.
            'available_at' => $this->clock->now($transaction->merchant)->subSecond(),
            'memo' => "Refund {$refund->uuid}",
        ]);
    }

    public function recordChargeback(SandboxDispute $dispute): SandboxBalanceEntry
    {
        $transaction = $dispute->transaction;

        return SandboxBalanceEntry::create([
            'sandbox_merchant_id' => $transaction->sandbox_merchant_id,
            'sandbox_transaction_id' => $transaction->id,
            'sandbox_dispute_id' => $dispute->id,
            'direction' => 'debit',
            'amount_minor' => $dispute->amount_minor,
            'currency' => $dispute->currency,
            'category' => 'chargeback',
            'available_at' => $this->clock->now($transaction->merchant)->subSecond(),
            'memo' => "Chargeback on {$transaction->uuid} ({$dispute->reason_code})",
        ]);
    }

    /**
     * Reverse a chargeback (dispute outcome = won) — credit the funds back.
     */
    public function reverseChargeback(SandboxDispute $dispute): SandboxBalanceEntry
    {
        $transaction = $dispute->transaction;

        return SandboxBalanceEntry::create([
            'sandbox_merchant_id' => $transaction->sandbox_merchant_id,
            'sandbox_transaction_id' => $transaction->id,
            'sandbox_dispute_id' => $dispute->id,
            'direction' => 'credit',
            'amount_minor' => $dispute->amount_minor,
            'currency' => $dispute->currency,
            'category' => 'chargeback',
            'available_at' => $this->clock->now($transaction->merchant)->subSecond(),
            'memo' => "Dispute won — funds returned ({$dispute->uuid})",
        ]);
    }

    /**
     * @return array{pending: int, available: int, total: int, currency: string}
     */
    public function balance(SandboxMerchant $merchant, ?CarbonImmutable $at = null): array
    {
        $at ??= $this->clock->now($merchant);

        $entries = SandboxBalanceEntry::query()
            ->where('sandbox_merchant_id', $merchant->id)
            ->get();

        $pending = 0;
        $available = 0;
        foreach ($entries as $entry) {
            $signed = $entry->direction === 'credit'
                ? (int) $entry->amount_minor
                : -((int) $entry->amount_minor);

            if ($entry->available_at <= $at) {
                $available += $signed;
            } else {
                $pending += $signed;
            }
        }

        return [
            'pending' => $pending,
            'available' => $available,
            'total' => $pending + $available,
            'currency' => $merchant->default_currency,
        ];
    }

    /**
     * When does this transaction's capture become spendable? Card
     * captures clear immediately in the sandbox (matches Stripe's
     * "balance available after fee deduction"); ACH and SEPA respect
     * realistic T+N windows.
     */
    protected function settlementAt(SandboxTransaction $transaction): CarbonImmutable
    {
        $now = $this->clock->now($transaction->merchant);

        return match ($transaction->method) {
            'ach' => $now->addDays(2),
            'sepa' => $now->addDays(1),
            'bnpl' => $now->addDays(3),
            default => $now->subSecond(),
        };
    }
}
