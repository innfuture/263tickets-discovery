<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Enums\SandboxState;
use App\Http\Controllers\Controller;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\StateMachine;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Mock BNPL hosted checkout pages (Klarna / Afterpay / Affirm). The
 * scenario flow points the payer at this URL after charge initiation;
 * the page renders provider-branded UI + an installment schedule
 * preview, and exposes Approve / Deny actions.
 *
 * Approve → flush queued webhooks immediately so the merchant sees
 *           payment.captured.
 * Deny    → transition to FAILED, fire loan.denied.
 *
 * Scenario hook: when the request resolves to one of these providers
 * the ScenarioResolver supplies a redirect URL pointing here.
 */
class BnplController extends Controller
{
    public function __construct(
        protected StateMachine $machine,
        protected WebhookDispatcher $webhooks,
    ) {}

    public function show(Request $request, string $provider): View
    {
        $this->assertProvider($provider);

        $data = $request->validate([
            'ref' => ['required', 'string', 'max:80'],
        ]);

        $transaction = SandboxTransaction::query()
            ->where('reference', $data['ref'])
            ->firstOrFail();

        return view('sandbox.bnpl.'.$provider, [
            'transaction' => $transaction,
            'provider' => $provider,
            'submitUrl' => route('payments.sandbox.bnpl.decide', ['provider' => $provider]),
            'installments' => $this->installmentScheduleFor($provider, (int) $transaction->amount_minor),
        ]);
    }

    public function decide(Request $request, string $provider): RedirectResponse
    {
        $this->assertProvider($provider);

        $data = $request->validate([
            'reference' => ['required', 'string'],
            'action' => ['required', 'in:approve,deny'],
        ]);

        $transaction = SandboxTransaction::query()
            ->where('reference', $data['reference'])
            ->firstOrFail();

        if ($data['action'] === 'approve') {
            // Hosted checkout success — flush the queued capture chain
            // immediately so the merchant sees the loan as funded.
            $this->webhooks->flushDue($transaction->merchant);
        } else {
            $this->machine->transition(
                transaction: $transaction,
                to: SandboxState::FAILED,
                actor: 'user',
                reason: 'bnpl_denied_by_payer',
            );
            $this->webhooks->enqueue(
                merchant: $transaction->merchant,
                transaction: $transaction,
                type: 'loan.denied',
                scheduledDelaySeconds: 0,
            );
        }

        return redirect()->away(
            $transaction->return_url
            ?? rtrim((string) config('app.url'), '/').'/sandbox/payments/dashboard',
        );
    }

    /**
     * Installment schedules differ per provider — these match the
     * real-world defaults: Klarna pay-in-3, Afterpay pay-in-4, Affirm
     * 3-month financing.
     *
     * @return array<int, array{due: string, amount: string}>
     */
    protected function installmentScheduleFor(string $provider, int $amountMinor): array
    {
        $today = now();
        $major = $amountMinor / 100;

        return match ($provider) {
            'klarna' => $this->splitEvenly($major, 3, fn (int $i) => $today->copy()->addDays($i * 30)->toDateString()),
            'afterpay' => $this->splitEvenly($major, 4, fn (int $i) => $today->copy()->addDays($i * 14)->toDateString()),
            'affirm' => $this->splitEvenly($major, 3, fn (int $i) => $today->copy()->addDays($i * 30)->toDateString()),
            default => [],
        };
    }

    /**
     * @return array<int, array{due: string, amount: string}>
     */
    protected function splitEvenly(float $total, int $parts, callable $dueFor): array
    {
        $per = number_format($total / $parts, 2, '.', '');

        return array_map(fn (int $i) => [
            'due' => $dueFor($i),
            'amount' => $per,
        ], range(0, $parts - 1));
    }

    protected function assertProvider(string $provider): void
    {
        abort_unless(in_array($provider, ['klarna', 'afterpay', 'affirm'], true), 404);
    }
}
