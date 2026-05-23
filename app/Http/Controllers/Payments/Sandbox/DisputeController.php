<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Enums\SandboxState;
use App\Http\Controllers\Controller;
use App\Models\SandboxDispute;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\SandboxKernel;
use App\Services\Payments\Sandbox\StateMachine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manual dispute initiation (§15 #9 — manual for P1; scheduler in P5).
 */
class DisputeController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function open(Request $request, SandboxTransaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'max:24'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
        ]);

        $dispute = $this->kernel->dispute(
            transaction: $transaction,
            reasonCode: $data['reason_code'],
            amountMinor: $data['amount_minor'] ?? null,
        );

        return response()->json(['dispute' => $this->serialise($dispute)], 201);
    }

    public function resolve(Request $request, SandboxDispute $dispute): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:won,lost'],
        ]);

        $transaction = $dispute->transaction;
        $state = $data['outcome'] === 'won'
            ? SandboxState::DISPUTE_WON
            : SandboxState::DISPUTE_LOST;

        app(StateMachine::class)->transition(
            transaction: $transaction,
            to: $state,
            actor: 'user',
            reason: "dispute_resolved:{$data['outcome']}",
        );

        $dispute->update([
            'status' => $data['outcome'],
            'resolved_at' => now(),
        ]);

        $this->kernel->webhooks()->enqueue(
            merchant: $transaction->merchant,
            transaction: $transaction,
            type: 'charge.dispute.closed',
            scheduledDelaySeconds: 1,
        );

        return response()->json(['dispute' => $this->serialise($dispute->fresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(SandboxDispute $dispute): array
    {
        return [
            'uuid' => $dispute->uuid,
            'transaction_uuid' => $dispute->transaction?->uuid,
            'amount' => number_format($dispute->amount_minor / 100, 2),
            'currency' => $dispute->currency,
            'reason_code' => $dispute->reason_code,
            'status' => $dispute->status,
            'evidence_due_at' => $dispute->evidence_due_at?->toIso8601String(),
            'resolved_at' => $dispute->resolved_at?->toIso8601String(),
        ];
    }
}
