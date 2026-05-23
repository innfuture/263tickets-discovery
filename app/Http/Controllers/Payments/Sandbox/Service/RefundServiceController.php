<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox\Service;

use App\Http\Controllers\Controller;
use App\Models\SandboxTransaction;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Data\RefundRequest;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RefundServiceController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string', 'max:80'],
            'provider_reference' => ['nullable', 'string', 'max:80'],
            'amount_minor' => ['nullable', 'integer', 'min:1'],
            'currency' => ['nullable', 'string', 'size:3'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $transaction = SandboxTransaction::query()
            ->when($data['reference'] ?? null, fn ($q, $v) => $q->where('reference', $v))
            ->when($data['provider_reference'] ?? null, fn ($q, $v) => $q->orWhere('provider_reference', $v))
            ->firstOrFail();

        $amount = isset($data['amount_minor'])
            ? new Money((int) $data['amount_minor'], strtoupper((string) ($data['currency'] ?? $transaction->currency)))
            : null;

        $refund = $this->kernel->refund($transaction, new RefundRequest(
            reference: $data['reference'] ?? $transaction->reference ?? $transaction->uuid,
            gatewayReference: $data['provider_reference'] ?? $transaction->provider_reference,
            amount: $amount,
            reason: $data['reason'],
        ));

        return response()->json([
            'uuid' => $refund->uuid,
            'transaction_uuid' => $transaction->uuid,
            'amount_minor' => (int) $refund->amount_minor,
            'currency' => $refund->currency,
            'state' => $refund->state,
            'response_body' => $refund->response_body,
        ], 201);
    }
}
