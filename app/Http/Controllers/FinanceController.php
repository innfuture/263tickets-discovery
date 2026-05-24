<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Event;
use App\Models\PaymentRefund;
use App\Models\PaymentTransaction;
use App\Models\TicketCategory;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Finance home — revenue, refunds queue, payouts summary.
 *
 * Revenue is computed as `sum_per_category(issued_tickets *
 * currency_price)` against the real schema:
 *
 *   - per-category sold count = COUNT(offline_tickets WHERE !voided)
 *   - per-category price      = ticket_currency_prices.price (major units)
 *     falling back to ticket_categories.base_price + base_currency when
 *     no per-currency override exists.
 *
 * Numbers reflect *gross potential* from issued inventory, not settled
 * bank transfers — explicit caveat shown in the UI footer.
 */
class FinanceController extends Controller
{
    public function index(Request $request, string $current_organization): Response
    {
        abort_unless($request->user()->can('finance.view-revenue'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $rangeDays = in_array((int) $request->query('range', 30), [7, 30, 90, 365], true)
            ? (int) $request->query('range', 30)
            : 30;
        $from = now()->subDays($rangeDays);

        // Event scoping — `?event=slug` narrows revenue to a single
        // event (refunds + payouts stay org-wide because the payment
        // transaction has no event_id link in the current schema).
        $eventSlug = trim((string) $request->query('event', ''));
        $eventScope = null;
        if ($eventSlug !== '') {
            $eventScope = Event::query()
                ->where('organisation_id', $org->id)
                ->where('slug', $eventSlug)
                ->first(['id', 'slug', 'name']);
        }

        $eventIds = $eventScope !== null
            ? [$eventScope->id]
            : Event::query()->where('organisation_id', $org->id)->pluck('id')->all();

        $revenueByCurrency = $this->revenueByCurrency($eventIds);

        // Refunds queue (payment_refunds table exists from the prior
        // payment gateway work). Scoped to this org via the parent
        // transaction's organization_id.
        $refunds = $this->refundsQueue($org->id, $from);

        // Payouts summary (payouts table from the platform migration).
        $payouts = $this->payoutsSummary($org->id);

        // Pick the currency with the largest revenue for the headline.
        $primaryCurrency = collect($revenueByCurrency)->sortByDesc('revenue')->first()['currency']
            ?? ($org->default_currency ?? 'USD');

        // Event options for the picker — alpha-sorted, capped to 200
        // (orgs with >200 active events should narrow some other way).
        $eventOptions = Event::query()
            ->where('organisation_id', $org->id)
            ->orderBy('name')
            ->limit(200)
            ->get(['slug', 'name'])
            ->map(fn (Event $e) => ['value' => $e->slug, 'label' => $e->name])
            ->all();

        return Inertia::render('finance/index', [
            'range_days' => $rangeDays,
            'primary_currency' => $primaryCurrency,
            'revenue_by_currency' => $revenueByCurrency,
            'totals' => [
                'units_sold' => (int) collect($revenueByCurrency)->sum('units_sold'),
                'refunds_open' => (int) ($refunds['open_count'] ?? 0),
                'refunds_processed_30d' => (int) ($refunds['processed_count'] ?? 0),
                'payouts_completed' => (int) ($payouts['completed_count'] ?? 0),
            ],
            'refunds' => $refunds['rows'],
            'payouts' => $payouts['rows'],
            'event_options' => $eventOptions,
            'event_scope' => $eventScope ? [
                'slug' => $eventScope->slug,
                'name' => $eventScope->name,
            ] : null,
            'permissions' => [
                'can_process_refund' => $request->user()->can('finance.process-refund'),
                'can_export' => $request->user()->can('finance.export-reports'),
            ],
            'breadcrumbs' => $eventScope
                ? [
                    ['title' => 'Finance', 'href' => "/{$current_organization}/finance"],
                    ['title' => $eventScope->name, 'href' => "/{$current_organization}/finance?event={$eventScope->slug}"],
                ]
                : [['title' => 'Finance', 'href' => "/{$current_organization}/finance"]],
        ]);
    }

    /**
     * Refund detail page — full payload, line of payment history, and
     * permission-aware action buttons. Org scoping is enforced via the
     * parent PaymentTransaction's organization_id.
     */
    public function showRefund(Request $request, string $current_organization, PaymentRefund $refund): Response
    {
        abort_unless($request->user()->can('finance.view-revenue'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);

        $transaction = $refund->transaction;
        abort_if($transaction === null || (int) $transaction->organization_id !== (int) $org->id, 404);

        return Inertia::render('finance/refund', [
            'refund' => [
                'uuid' => $refund->uuid,
                'reference' => $refund->reference,
                'gateway_reference' => $refund->gateway_reference,
                'amount_minor' => (int) $refund->amount_minor,
                'currency' => $refund->currency,
                'status' => $refund->status,
                'reason' => $refund->reason,
                'response_body' => $refund->response_body,
                'created_at' => $refund->created_at?->toIso8601String(),
                'updated_at' => $refund->updated_at?->toIso8601String(),
                'initiator' => $refund->initiator ? [
                    'name' => $refund->initiator->name,
                    'email' => $refund->initiator->email,
                ] : null,
            ],
            'transaction' => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'gateway' => $transaction->gateway,
                'gateway_reference' => $transaction->gateway_reference,
                'status' => $transaction->status,
                'amount_minor' => (int) $transaction->amount_minor,
                'currency' => $transaction->currency,
                'customer_email' => $transaction->customer_email,
                'customer_msisdn' => $transaction->customer_msisdn,
                'customer_name' => $transaction->customer_name,
                'created_at' => $transaction->created_at?->toIso8601String(),
                'settled_at' => $transaction->settled_at?->toIso8601String(),
            ],
            'permissions' => [
                'can_process' => $request->user()->can('finance.process-refund'),
            ],
            'breadcrumbs' => [
                ['title' => 'Finance', 'href' => "/{$current_organization}/finance"],
                ['title' => "Refund {$refund->reference}", 'href' => "/{$current_organization}/finance/refunds/{$refund->uuid}"],
            ],
        ]);
    }

    /**
     * Mark a pending refund as succeeded. In production this would
     * dispatch a gateway-side refund call via PaymentManager; here we
     * flip the status, audit, and notify the parent transaction.
     */
    public function processRefund(Request $request, string $current_organization, PaymentRefund $refund, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('finance.process-refund'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);
        abort_if((int) ($refund->transaction?->organization_id ?? 0) !== (int) $org->id, 404);
        abort_unless($refund->status === 'pending', 409, 'Only pending refunds can be processed.');

        DB::transaction(function () use ($refund) {
            $refund->update(['status' => 'succeeded']);

            $tx = $refund->transaction;
            $alreadyRefunded = (int) PaymentRefund::query()
                ->where('payment_transaction_id', $tx->id)
                ->where('status', 'succeeded')
                ->sum('amount_minor');

            $newStatus = $alreadyRefunded >= (int) $tx->amount_minor
                ? PaymentStatus::REFUNDED
                : PaymentStatus::PARTIALLY_REFUNDED;
            $tx->markStatus($newStatus);
        });

        $audit->record('finance.refund.processed', $org, $request->user(), after: [
            'refund_uuid' => $refund->uuid,
            'amount_minor' => $refund->amount_minor,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund marked as succeeded.']);

        return back();
    }

    /**
     * Deny a pending refund. Flips status to failed; transaction stays
     * captured. Common path when fraud-review or chargeback policy
     * blocks the refund.
     */
    public function denyRefund(Request $request, string $current_organization, PaymentRefund $refund, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->can('finance.process-refund'), 403);

        $org = $request->user()->currentOrganization;
        abort_if($org === null, 404);
        abort_if((int) ($refund->transaction?->organization_id ?? 0) !== (int) $org->id, 404);
        abort_unless($refund->status === 'pending', 409, 'Only pending refunds can be denied.');

        $refund->update(['status' => 'failed']);

        $audit->record('finance.refund.denied', $org, $request->user(), after: [
            'refund_uuid' => $refund->uuid,
            'amount_minor' => $refund->amount_minor,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund denied.']);

        return back();
    }

    /**
     * Gross revenue per currency. For each ticket_category:
     *   - sold = COUNT(offline_tickets WHERE ticket_category_id = X AND !voided)
     *   - revenue contribution per currency = sold * (currency_override OR base_price)
     *
     * Implemented as two passes — sold counts in one grouped query,
     * currency-aware totals in PHP — to keep the SQL portable across
     * MySQL strict modes.
     *
     * @param  array<int, int>  $eventIds
     * @return array<int, array{currency: string, revenue: float, units_sold: int, revenue_formatted: string}>
     */
    protected function revenueByCurrency(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $categories = TicketCategory::query()
            ->whereIn('event_id', $eventIds)
            ->get(['id', 'base_price', 'base_currency'])
            ->keyBy('id');

        if ($categories->isEmpty()) {
            return [];
        }

        // sold per category — one row per category, single grouped query.
        $sold = DB::table('offline_tickets')
            ->whereIn('ticket_category_id', $categories->keys()->all())
            ->where('is_voided', false)
            ->selectRaw('ticket_category_id, COUNT(*) AS units')
            ->groupBy('ticket_category_id')
            ->pluck('units', 'ticket_category_id')
            ->all();

        // Per-currency overrides, keyed [category_id][currency_code] => price.
        $overrides = DB::table('ticket_currency_prices')
            ->whereIn('ticket_category_id', $categories->keys()->all())
            ->get(['ticket_category_id', 'currency_code', 'price'])
            ->groupBy('ticket_category_id')
            ->map(fn ($rows) => $rows->pluck('price', 'currency_code')->all())
            ->all();

        // Build the rollup. Each category contributes to its base
        // currency by default plus every override currency it carries.
        $bucket = []; // [currency => ['revenue' => float, 'units_sold' => int]]
        foreach ($categories as $catId => $cat) {
            $units = (int) ($sold[$catId] ?? 0);
            if ($units === 0) {
                continue;
            }

            // Base currency contribution.
            $baseCurrency = (string) $cat->base_currency;
            $basePrice = (float) $cat->base_price;
            $bucket[$baseCurrency] = $bucket[$baseCurrency] ?? ['revenue' => 0.0, 'units_sold' => 0];
            $bucket[$baseCurrency]['revenue'] += $units * $basePrice;
            $bucket[$baseCurrency]['units_sold'] += $units;

            // Override currencies — each adds an alternate revenue
            // figure (organizer chose to also accept this currency).
            foreach ($overrides[$catId] ?? [] as $code => $price) {
                if ($code === $baseCurrency) {
                    continue;
                }
                $bucket[$code] = $bucket[$code] ?? ['revenue' => 0.0, 'units_sold' => 0];
                $bucket[$code]['revenue'] += $units * (float) $price;
                $bucket[$code]['units_sold'] += $units;
            }
        }

        $out = [];
        foreach ($bucket as $currency => $r) {
            $out[] = [
                'currency' => (string) $currency,
                'revenue' => round($r['revenue'], 2),
                'units_sold' => $r['units_sold'],
                'revenue_formatted' => number_format($r['revenue'], 2),
            ];
        }

        usort($out, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        return $out;
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, open_count: int, processed_count: int}
     */
    protected function refundsQueue(int $orgId, \DateTimeInterface $from): array
    {
        if (! Schema::hasTable('payment_refunds')) {
            return ['rows' => [], 'open_count' => 0, 'processed_count' => 0];
        }

        $rows = DB::table('payment_refunds AS r')
            ->join('payment_transactions AS t', 't.id', '=', 'r.payment_transaction_id')
            ->where('t.organization_id', $orgId)
            ->orderByDesc('r.created_at')
            ->limit(25)
            ->get(['r.uuid', 'r.amount_minor', 'r.currency', 'r.status', 'r.reason', 'r.created_at', 't.reference', 't.gateway']);

        $openCount = DB::table('payment_refunds AS r')
            ->join('payment_transactions AS t', 't.id', '=', 'r.payment_transaction_id')
            ->where('t.organization_id', $orgId)
            ->where('r.status', 'pending')
            ->count();

        $processedCount = DB::table('payment_refunds AS r')
            ->join('payment_transactions AS t', 't.id', '=', 'r.payment_transaction_id')
            ->where('t.organization_id', $orgId)
            ->where('r.created_at', '>=', $from)
            ->whereIn('r.status', ['succeeded'])
            ->count();

        return [
            'rows' => $rows->map(fn ($r) => [
                'uuid' => $r->uuid,
                'amount_minor' => (int) $r->amount_minor,
                'currency' => (string) $r->currency,
                'status' => (string) $r->status,
                'reason' => $r->reason,
                'created_at' => $r->created_at,
                'reference' => $r->reference,
                'gateway' => $r->gateway,
            ])->all(),
            'open_count' => $openCount,
            'processed_count' => $processedCount,
        ];
    }

    /**
     * @return array{rows: array<int, array<string, mixed>>, completed_count: int}
     */
    protected function payoutsSummary(int $orgId): array
    {
        if (! Schema::hasTable('payouts')) {
            return ['rows' => [], 'completed_count' => 0];
        }

        $rows = DB::table('payouts')
            ->where('organization_id', $orgId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $completed = DB::table('payouts')
            ->where('organization_id', $orgId)
            ->where('status', 'completed')
            ->count();

        return [
            'rows' => $rows->map(fn ($p) => [
                'id' => (int) $p->id,
                'amount_minor' => (int) ($p->amount_cents ?? 0),
                'currency' => (string) ($p->currency ?? 'USD'),
                'status' => (string) ($p->status ?? 'unknown'),
                // Real schema exposes `bank_last4` for the destination — wrap
                // it for the UI; full payout-destination metadata lives in
                // the Stripe payout object linked by `stripe_payout_id`.
                'destination_label' => ! empty($p->bank_last4) ? '•••• '.$p->bank_last4 : null,
                'arrival_on' => $p->arrival_date ?? null,
                'created_at' => $p->created_at ?? null,
            ])->all(),
            'completed_count' => $completed,
        ];
    }
}
