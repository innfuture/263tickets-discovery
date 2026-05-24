<?php

declare(strict_types=1);

namespace App\Http\Controllers\BackOffice\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Storefront\ApprovalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalQueueController extends Controller
{
    public function __construct(protected ApprovalQueueService $queue) {}

    public function index(Organization $currentOrganization): JsonResponse
    {
        return response()->json([
            'data' => Order::query()
                ->where('organisation_id', $currentOrganization->uuid)
                ->where('status', 'pending_approval')
                ->with('event:id,name,slug,starts_at')
                ->orderBy('placed_at')
                ->limit(200)
                ->get([
                    'id', 'uuid', 'reference', 'event_id', 'buyer_name',
                    'buyer_email', 'total_cents', 'currency', 'placed_at',
                    'metadata',
                ]),
        ]);
    }

    public function approve(Organization $currentOrganization, Order $order, Request $request): JsonResponse
    {
        abort_if($order->organisation_id !== $currentOrganization->uuid, 404);
        $this->queue->approve($order, $request->user());

        return response()->json(['data' => ['reference' => $order->reference, 'status' => 'paid']]);
    }

    public function reject(Organization $currentOrganization, Order $order, Request $request): JsonResponse
    {
        abort_if($order->organisation_id !== $currentOrganization->uuid, 404);
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);
        $this->queue->reject($order, $request->user(), $validated['reason'] ?? null);

        return response()->json(['data' => ['reference' => $order->reference, 'status' => 'cancelled']]);
    }
}
