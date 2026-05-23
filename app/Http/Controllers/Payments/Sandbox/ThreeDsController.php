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
 * Mock 3DS Access Control Server (§5.1, §5.5). The ScenarioResolver
 * builds redirect URLs that land here; the page presents an Approve /
 * Deny button and either:
 *
 *   Approve → fire the queued capture webhook now → transaction
 *             transitions to CAPTURED via WebhookDispatcher's follow-up.
 *   Deny    → transition straight to FAILED, fire payment.failed.
 *
 * The original transaction reference is the trust boundary — we look
 * up by reference + scenario to prevent a stray URL from manipulating
 * an unrelated transaction.
 */
class ThreeDsController extends Controller
{
    public function __construct(
        protected StateMachine $machine,
        protected WebhookDispatcher $webhooks,
    ) {}

    public function show(Request $request): View
    {
        $data = $request->validate([
            'ref' => ['required', 'string', 'max:80'],
            'accept' => ['required', 'in:0,1'],
        ]);

        $transaction = SandboxTransaction::query()
            ->where('reference', $data['ref'])
            ->firstOrFail();

        return view('sandbox.three-ds-acs', [
            'transaction' => $transaction,
            'accept' => (bool) (int) $data['accept'],
            'submitUrl' => route('payments.sandbox.three-ds.submit'),
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string'],
            'action' => ['required', 'in:approve,deny'],
        ]);

        $transaction = SandboxTransaction::query()
            ->where('reference', $data['reference'])
            ->firstOrFail();

        if ($data['action'] === 'approve') {
            // Flush any queued webhooks for this transaction
            // immediately so the host app sees the capture now.
            $this->webhooks->flushDue($transaction->merchant);
        } else {
            $this->machine->transition(
                transaction: $transaction,
                to: SandboxState::FAILED,
                actor: 'user',
                reason: '3ds_denied',
            );
            $this->webhooks->enqueue(
                merchant: $transaction->merchant,
                transaction: $transaction,
                type: 'payment.failed',
                scheduledDelaySeconds: 0,
            );
        }

        return redirect()->away(
            $transaction->return_url
            ?? rtrim((string) config('app.url'), '/').'/sandbox/payments/dashboard',
        );
    }
}
