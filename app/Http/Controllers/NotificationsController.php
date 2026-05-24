<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\OfflineTicket;
use App\Models\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Notifications inbox — derived in-memory from current org state
 * because the codebase doesn't have a notification table dedicated to
 * organizer-side alerts (only PaymentWebhookEvent / audit_events).
 *
 * Each notification has:
 *   - id           stable hash so the UI can mark-as-read locally
 *   - severity     info | warning | critical
 *   - category     events | sales | payments | operations
 *   - title, body
 *   - link         deep link into the relevant page
 *   - occurred_at  ISO timestamp
 *
 * Rules currently surfaced:
 *   - upcoming event starting within 72h (info)
 *   - event sales > 80% (warning) / sold_out (critical)
 *   - draft event past sales_start_at (warning)
 *   - failed payment webhooks last 24h (critical)
 *   - pending refunds (warning)
 *   - voided tickets > 5 today (info)
 */
class NotificationsController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $items = [];

        $items = array_merge($items, $this->eventStartingSoon($org->id, $current_organization));
        $items = array_merge($items, $this->capacityAlerts($org->id, $current_organization));
        $items = array_merge($items, $this->draftPastSalesStart($org->id, $current_organization));
        $items = array_merge($items, $this->failedPayments($org->id, $current_organization));
        $items = array_merge($items, $this->openRefunds($org->id, $current_organization));
        $items = array_merge($items, $this->highVoidDay($org->id, $current_organization));

        // Newest first.
        usort($items, fn ($a, $b) => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));

        $counts = [
            'total' => count($items),
            'critical' => count(array_filter($items, fn ($i) => $i['severity'] === 'critical')),
            'warning' => count(array_filter($items, fn ($i) => $i['severity'] === 'warning')),
            'info' => count(array_filter($items, fn ($i) => $i['severity'] === 'info')),
        ];

        return Inertia::render('notifications/index', [
            'items' => $items,
            'counts' => $counts,
            'breadcrumbs' => [
                ['title' => 'Notifications', 'href' => "/{$current_organization}/notifications"],
            ],
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function eventStartingSoon(int $orgId, string $orgSlug): array
    {
        $cutoff = now()->addHours(72);

        return Event::query()
            ->where('organisation_id', $orgId)
            ->whereBetween('starts_at', [now(), $cutoff])
            ->orderBy('starts_at')
            ->get(['slug', 'name', 'starts_at'])
            ->map(fn (Event $e) => [
                'id' => 'event-soon-'.$e->slug,
                'severity' => 'info',
                'category' => 'events',
                'title' => $e->name.' starts '.$e->starts_at?->diffForHumans(),
                'body' => 'Prepare check-in staff and confirm staffing before doors open.',
                'link' => "/{$orgSlug}/events/{$e->slug}",
                'occurred_at' => $e->starts_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function capacityAlerts(int $orgId, string $orgSlug): array
    {
        $eventIds = Event::where('organisation_id', $orgId)->pluck('id');
        if ($eventIds->isEmpty()) {
            return [];
        }

        $rows = TicketCategory::query()
            ->whereIn('event_id', $eventIds)
            ->with('event:id,slug,name')
            ->get(['id', 'event_id', 'name', 'offline_quantity', 'online_quantity']);

        // Sold counts per category in a single grouped query — keyed
        // for O(1) lookup in the loop below.
        $sold = OfflineTicket::query()
            ->whereIn('ticket_category_id', $rows->pluck('id')->all())
            ->where('is_voided', false)
            ->selectRaw('ticket_category_id, COUNT(*) AS units')
            ->groupBy('ticket_category_id')
            ->pluck('units', 'ticket_category_id')
            ->all();

        $items = [];
        foreach ($rows as $cat) {
            $capacity = (int) $cat->offline_quantity + (int) $cat->online_quantity;
            if ($capacity <= 0) {
                continue;
            }
            $soldUnits = (int) ($sold[$cat->id] ?? 0);
            if ($soldUnits <= 0) {
                continue;
            }
            $ratio = $soldUnits / $capacity;
            if ($ratio >= 1) {
                $items[] = [
                    'id' => 'cat-soldout-'.$cat->id,
                    'severity' => 'critical',
                    'category' => 'sales',
                    'title' => "Sold out: {$cat->event?->name} · {$cat->name}",
                    'body' => 'Release more inventory or promote upsell tier.',
                    'link' => "/{$orgSlug}/events/{$cat->event?->slug}/tickets",
                    'occurred_at' => now()->toIso8601String(),
                ];
            } elseif ($ratio >= 0.8) {
                $items[] = [
                    'id' => 'cat-near-'.$cat->id,
                    'severity' => 'warning',
                    'category' => 'sales',
                    'title' => round($ratio * 100).'% sold: '.$cat->event?->name.' · '.$cat->name,
                    'body' => 'Consider adding a higher-tier release or pausing ads.',
                    'link' => "/{$orgSlug}/events/{$cat->event?->slug}/tickets",
                    'occurred_at' => now()->toIso8601String(),
                ];
            }
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function draftPastSalesStart(int $orgId, string $orgSlug): array
    {
        return Event::query()
            ->where('organisation_id', $orgId)
            ->where('status', 'draft')
            ->whereNotNull('sales_start_at')
            ->where('sales_start_at', '<', now())
            ->get(['slug', 'name', 'sales_start_at'])
            ->map(fn (Event $e) => [
                'id' => 'event-draft-late-'.$e->slug,
                'severity' => 'warning',
                'category' => 'events',
                'title' => "{$e->name} is still draft past its sales-start time",
                'body' => 'Publish the event or push sales_start_at later.',
                'link' => "/{$orgSlug}/events/{$e->slug}/edit",
                'occurred_at' => $e->sales_start_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function failedPayments(int $orgId, string $orgSlug): array
    {
        if (! Schema::hasTable('payment_transactions')) {
            return [];
        }

        $rows = DB::table('payment_transactions')
            ->where('organization_id', $orgId)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['uuid', 'reference', 'gateway', 'currency', 'amount_minor', 'created_at']);

        return $rows->map(fn ($r) => [
            'id' => 'pmt-failed-'.$r->uuid,
            'severity' => 'critical',
            'category' => 'payments',
            'title' => 'Payment failed: '.($r->reference ?? $r->uuid),
            'body' => 'Gateway '.$r->gateway.' returned a decline. Customer needs follow-up.',
            'link' => "/{$orgSlug}/finance",
            'occurred_at' => (string) $r->created_at,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function openRefunds(int $orgId, string $orgSlug): array
    {
        if (! Schema::hasTable('payment_refunds')) {
            return [];
        }

        $rows = DB::table('payment_refunds AS r')
            ->join('payment_transactions AS t', 't.id', '=', 'r.payment_transaction_id')
            ->where('t.organization_id', $orgId)
            ->where('r.status', 'pending')
            ->orderByDesc('r.created_at')
            ->limit(10)
            ->get(['r.uuid', 'r.amount_minor', 'r.currency', 'r.created_at', 't.reference']);

        return $rows->map(fn ($r) => [
            'id' => 'refund-open-'.$r->uuid,
            'severity' => 'warning',
            'category' => 'payments',
            'title' => 'Refund pending: '.($r->reference ?? $r->uuid),
            'body' => 'Process or deny the refund from the Finance page.',
            'link' => "/{$orgSlug}/finance",
            'occurred_at' => (string) $r->created_at,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function highVoidDay(int $orgId, string $orgSlug): array
    {
        $count = OfflineTicket::query()
            ->where('organisation_id', $orgId)
            ->whereDate('voided_at', now()->toDateString())
            ->count();

        if ($count < 5) {
            return [];
        }

        return [[
            'id' => 'voids-spike-'.now()->toDateString(),
            'severity' => 'info',
            'category' => 'operations',
            'title' => "{$count} tickets voided today",
            'body' => 'Above the usual baseline — check if the events page surfaced an issue.',
            'link' => "/{$orgSlug}/attendees?status=voided",
            'occurred_at' => now()->toIso8601String(),
        ]];
    }
}
