<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\Payments\ReconcilePaymentStatusJob;
use App\Models\PaymentTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SPA-friendly status polling endpoint. The front-end polls this URL
 * after launching a hosted-page or USSD push flow. If the transaction
 * is still PENDING and stale, we synchronously dispatch a reconcile job
 * so the next poll sees fresh data.
 */
class StatusController extends Controller
{
    public function show(Request $request, PaymentTransaction $transaction): JsonResponse
    {
        // Org-scoped access. Service-to-service callers (no user)
        // bypass this check — gate those at the route middleware level.
        if ($request->user()) {
            abort_unless(
                $transaction->organization_id === null
                || $transaction->organization_id === $request->user()->currentOrganization?->id,
                404,
            );
        }

        if ($transaction->status === 'pending'
            && $transaction->created_at?->diffInMinutes(now()) >= 1) {
            ReconcilePaymentStatusJob::dispatch($transaction->id);
        }

        return response()->json([
            'transaction' => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'gateway' => $transaction->gateway,
                'status' => $transaction->status,
                'amount' => number_format($transaction->amount_minor / 100, 2),
                'currency' => $transaction->currency,
                'settled_at' => $transaction->settled_at?->toIso8601String(),
                'failed_at' => $transaction->failed_at?->toIso8601String(),
                'instructions' => $transaction->instructions,
            ],
        ]);
    }
}
