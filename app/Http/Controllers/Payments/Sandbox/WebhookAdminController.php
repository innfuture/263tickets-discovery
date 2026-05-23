<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Sandbox\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookAdminController extends Controller
{
    public function __construct(protected WebhookDispatcher $webhooks) {}

    public function index(Request $request): JsonResponse
    {
        $rows = SandboxWebhookOutbox::query()
            ->when($request->query('merchant'), fn ($q, $slug) => $q->whereHas('merchant', fn ($m) => $m->where('slug', $slug)))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'events' => $rows->map(fn (SandboxWebhookOutbox $r) => [
                'uuid' => $r->uuid,
                'event_id' => $r->event_id,
                'type' => $r->type,
                'emulate' => $r->emulate,
                'status' => $r->status,
                'attempts' => $r->attempts,
                'scheduled_for' => $r->scheduled_for?->toIso8601String(),
                'response_status' => $r->response_status,
            ])->all(),
        ]);
    }

    public function replay(SandboxWebhookOutbox $event): JsonResponse
    {
        $this->webhooks->replay($event);

        return response()->json(['status' => 'replayed', 'event_id' => $event->event_id]);
    }

    public function drop(SandboxWebhookOutbox $event): JsonResponse
    {
        $this->webhooks->drop($event);

        return response()->json(['status' => 'dropped']);
    }

    public function inject(Request $request, SandboxMerchant $merchant): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
            'transaction_uuid' => ['nullable', 'string'],
        ]);

        $transaction = isset($data['transaction_uuid'])
            ? SandboxTransaction::query()->where('uuid', $data['transaction_uuid'])->first()
            : null;

        $event = $this->webhooks->inject(
            merchant: $merchant,
            type: $data['type'],
            payload: $data['payload'],
            transaction: $transaction,
        );

        return response()->json(['status' => 'queued', 'event_id' => $event->event_id], 201);
    }

    public function flush(SandboxMerchant $merchant): JsonResponse
    {
        $delivered = $this->webhooks->flushDue($merchant);

        return response()->json([
            'merchant' => $merchant->slug,
            'delivered' => count($delivered),
        ]);
    }
}
