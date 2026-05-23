<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxStateLog;
use App\Models\SandboxTransaction;
use App\Services\Payments\Sandbox\Recorder;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inspector — transaction detail (§4.3). Tabs:
 *
 *   Timeline    — state_log entries with virtual & wall times
 *   Request     — sanitised inbound body
 *   Response    — sandbox-generated response body
 *   Webhooks    — outbox rows for this transaction
 *   Diff        — Recorder-driven comparison vs. recorded production
 *
 * Diff is its own JSON endpoint so the page can fetch lazily — the
 * comparison can be large and we don't want it bloating the initial
 * page payload.
 */
class InspectorController extends Controller
{
    public function __construct(protected Recorder $recorder) {}

    public function show(SandboxTransaction $transaction): Response
    {
        $log = SandboxStateLog::query()
            ->where('sandbox_transaction_id', $transaction->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (SandboxStateLog $row) => [
                'from_state' => $row->from_state,
                'to_state' => $row->to_state,
                'reason' => $row->reason,
                'actor' => $row->actor,
                'occurred_at' => $row->occurred_at?->toIso8601String(),
                'virtual_time_at' => $row->virtual_time_at?->toIso8601String(),
                'context' => $row->context,
            ])
            ->all();

        $webhooks = $transaction->webhookEvents()
            ->orderBy('id')
            ->get()
            ->map(fn ($w) => [
                'uuid' => $w->uuid,
                'event_id' => $w->event_id,
                'type' => $w->type,
                'emulate' => $w->emulate,
                'status' => $w->status,
                'attempts' => $w->attempts,
                'scheduled_for' => $w->scheduled_for?->toIso8601String(),
                'last_attempt_at' => $w->last_attempt_at?->toIso8601String(),
                'response_status' => $w->response_status,
                'signature_present' => ! empty($w->signature),
            ])
            ->all();

        $refunds = $transaction->refunds()
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => [
                'uuid' => $r->uuid,
                'amount_minor' => (int) $r->amount_minor,
                'state' => $r->state,
                'reason' => $r->reason,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();

        $disputes = $transaction->disputes()
            ->orderBy('id')
            ->get()
            ->map(fn ($d) => [
                'uuid' => $d->uuid,
                'reason_code' => $d->reason_code,
                'status' => $d->status,
                'evidence_due_at' => $d->evidence_due_at?->toIso8601String(),
                'resolved_at' => $d->resolved_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('sandbox/payments/inspector', [
            'transaction' => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'provider_reference' => $transaction->provider_reference,
                'emulate' => $transaction->emulate,
                'method' => $transaction->method,
                'state' => $transaction->state,
                'scenario' => $transaction->scenario,
                'reason_code' => $transaction->reason_code,
                'amount' => number_format($transaction->amount_minor / 100, 2),
                'amount_captured' => number_format($transaction->amount_captured_minor / 100, 2),
                'amount_refunded' => number_format($transaction->amount_refunded_minor / 100, 2),
                'currency' => $transaction->currency,
                'redirect_url' => $transaction->redirect_url,
                'authorized_at' => $transaction->authorized_at?->toIso8601String(),
                'captured_at' => $transaction->captured_at?->toIso8601String(),
                'failed_at' => $transaction->failed_at?->toIso8601String(),
                'settlement_at' => $transaction->settlement_at?->toIso8601String(),
                'expires_at' => $transaction->expires_at?->toIso8601String(),
                'request_body' => $transaction->request_body,
                'response_body' => $transaction->response_body,
                'metadata' => $transaction->metadata,
            ],
            'merchant' => [
                'slug' => $transaction->merchant->slug ?? null,
                'name' => $transaction->merchant->name ?? null,
            ],
            'timeline' => $log,
            'webhooks' => $webhooks,
            'refunds' => $refunds,
            'disputes' => $disputes,
            'has_recording' => $this->recorder->find(
                $transaction->emulate,
                'charge',
                $transaction->scenario,
            ) !== null,
            'breadcrumbs' => [
                ['title' => 'Sandbox', 'href' => '/sandbox/payments/dashboard'],
                ['title' => 'Inspector', 'href' => "/sandbox/payments/transactions/{$transaction->uuid}/inspect"],
            ],
        ]);
    }

    public function diff(SandboxTransaction $transaction): JsonResponse
    {
        $diff = $this->recorder->diff(
            emulate: $transaction->emulate,
            operation: 'charge',
            scenario: $transaction->scenario,
            sandboxBody: (array) $transaction->response_body,
        );

        if ($diff === null) {
            return response()->json([
                'has_recording' => false,
                'diff' => [],
            ]);
        }

        return response()->json([
            'has_recording' => true,
            'diff' => collect($diff)
                ->map(fn (array $pair, string $path) => [
                    'path' => $path,
                    'recorded' => $pair['recorded'],
                    'sandbox' => $pair['sandbox'],
                ])
                ->values()
                ->all(),
        ]);
    }
}
