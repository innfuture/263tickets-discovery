<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox\Service;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\SandboxKernel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatusServiceController extends Controller
{
    public function __construct(protected SandboxKernel $kernel) {}

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['nullable', 'string'],
            'provider_reference' => ['nullable', 'string'],
        ]);

        if (empty($data['reference']) && empty($data['provider_reference'])) {
            return response()->json(['error' => 'reference_required'], 422);
        }

        $transaction = SandboxTransaction::query()
            ->when($data['reference'] ?? null, fn ($q, $v) => $q->where('reference', $v))
            ->when($data['provider_reference'] ?? null, fn ($q, $v) => $q->orWhere('provider_reference', $v))
            ->first();

        if ($transaction === null) {
            return response()->json([
                'reference' => $data['reference'] ?? null,
                'payment_status' => PaymentStatus::FAILED->value,
                'message' => 'not_found',
            ], 404);
        }

        $result = $this->kernel->status($transaction);

        return response()->json([
            'reference' => $result->reference,
            'provider_reference' => $result->gatewayReference,
            'payment_status' => $result->status->value,
            'state' => $transaction->state,
            'message' => $result->message,
            'response_body' => $result->raw,
        ]);
    }
}
