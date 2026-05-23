<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-transaction admin endpoints — capture, void, view. Refund is on
 * its own controller to keep the route module small.
 */
class TransactionController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function show(SandboxTransaction $transaction): JsonResponse
    {
        return response()->json(['transaction' => $this->serialise($transaction)]);
    }

    public function capture(Request $request, SandboxTransaction $transaction): JsonResponse
    {
        $data = $request->validate([
            'amount_minor' => ['nullable', 'integer', 'min:1'],
        ]);

        $captured = $this->kernel->capture($transaction, $data['amount_minor'] ?? null);

        return response()->json(['transaction' => $this->serialise($captured)]);
    }

    public function void(SandboxTransaction $transaction): JsonResponse
    {
        $voided = $this->kernel->void($transaction);

        return response()->json(['transaction' => $this->serialise($voided)]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function serialise(SandboxTransaction $t): array
    {
        return [
            'uuid' => $t->uuid,
            'reference' => $t->reference,
            'provider_reference' => $t->provider_reference,
            'emulate' => $t->emulate,
            'method' => $t->method,
            'state' => $t->state,
            'payment_status' => $t->stateEnum->toPaymentStatus()->value,
            'scenario' => $t->scenario,
            'reason_code' => $t->reason_code,
            'amount' => number_format($t->amount_minor / 100, 2),
            'amount_captured' => number_format($t->amount_captured_minor / 100, 2),
            'amount_refunded' => number_format($t->amount_refunded_minor / 100, 2),
            'currency' => $t->currency,
            'redirect_url' => $t->redirect_url,
            'authorized_at' => $t->authorized_at?->toIso8601String(),
            'captured_at' => $t->captured_at?->toIso8601String(),
            'failed_at' => $t->failed_at?->toIso8601String(),
        ];
    }
}
