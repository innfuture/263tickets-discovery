<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments\Sandbox;

use App\Enums\SandboxScenario;
use App\Enums\SandboxState;
use App\Http\Controllers\Controller;
use App\Models\SandboxMerchant;
use App\Models\SandboxTransaction;
use App\Models\SandboxWebhookOutbox;
use App\Services\Payments\Sandbox\BalanceLedger;
use App\Services\Payments\Sandbox\MagicValues;
use App\Services\Payments\Sandbox\SandboxClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sandbox dashboard (§4.1–§4.5). One Inertia page with five panels:
 *
 *   - Stats: volume, auth count, capture count, dispute count
 *   - Scenario panel: trigger any scenario via UI form
 *   - Transactions table: recent activity, click for detail
 *   - Webhook console: outbox view + replay buttons
 *   - Magic values: searchable catalog
 *
 * Read-only to start — mutations go through the existing REST surface.
 */
class DashboardController extends Controller
{
    public function __construct(
        protected SandboxClock $clock,
        protected BalanceLedger $ledger,
        protected MagicValues $magic,
    ) {}

    public function index(Request $request): Response
    {
        $merchant = $this->resolveMerchant($request);

        $stats = $this->statsFor($merchant);
        $transactions = $this->recentTransactions($merchant);
        $webhooks = $this->recentWebhooks($merchant);

        return Inertia::render('sandbox/payments/dashboard', [
            'merchant' => [
                'slug' => $merchant->slug,
                'name' => $merchant->name,
                'emulate_default' => $merchant->emulate_default,
                'default_currency' => $merchant->default_currency,
                'webhook_endpoint' => $merchant->webhook_endpoint,
            ],
            'merchants' => SandboxMerchant::query()
                ->orderBy('name')
                ->get(['slug', 'name'])
                ->all(),
            'clock' => [
                'virtual_time' => $this->clock->now($merchant)->toIso8601String(),
                'wall_time' => now()->toIso8601String(),
            ],
            'balance' => array_merge(
                $this->ledger->balance($merchant),
                ['currency' => $merchant->default_currency],
            ),
            'stats' => $stats,
            'transactions' => $transactions,
            'webhooks' => $webhooks,
            'scenarios' => array_map(fn (SandboxScenario $s) => $s->value, SandboxScenario::cases()),
            'magic' => [
                'cards' => collect($this->magic->cards())
                    ->map(fn ($v, $k) => ['pan' => $k, 'scenario' => $v['scenario']->value, 'brand' => $v['brand']])
                    ->values()
                    ->all(),
                'msisdns' => collect($this->magic->msisdnSuffixes())
                    ->map(fn (SandboxScenario $s, $suffix) => ['suffix' => (string) $suffix, 'scenario' => $s->value])
                    ->values()
                    ->all(),
                'ach' => collect($this->magic->achAccountSuffixes())
                    ->map(fn (SandboxScenario $s, $acct) => ['account' => (string) $acct, 'scenario' => $s->value])
                    ->values()
                    ->all(),
            ],
            'breadcrumbs' => [
                ['title' => 'Sandbox', 'href' => '/sandbox/payments/dashboard'],
                ['title' => 'Payments', 'href' => '/sandbox/payments/dashboard'],
            ],
        ]);
    }

    protected function resolveMerchant(Request $request): SandboxMerchant
    {
        $slug = (string) $request->query('merchant', (string) config('payments.sandbox.default_merchant'));

        return SandboxMerchant::query()->where('slug', $slug)->first()
            ?? SandboxMerchant::firstOrCreate(
                ['slug' => 'sandbox-default'],
                ['name' => 'Sandbox default', 'emulate_default' => 'stripe', 'default_currency' => 'USD'],
            );
    }

    /**
     * @return array<string, int|string>
     */
    protected function statsFor(SandboxMerchant $merchant): array
    {
        $base = SandboxTransaction::query()->where('sandbox_merchant_id', $merchant->id);
        $today = (clone $base)->where('created_at', '>=', now()->startOfDay());

        return [
            'volume_today_minor' => (int) (clone $today)
                ->whereIn('state', [SandboxState::CAPTURED->value, SandboxState::PART_REFUNDED->value])
                ->sum('amount_minor'),
            'authorized_today' => (int) (clone $today)->where('state', SandboxState::AUTHORIZED->value)->count(),
            'captured_today' => (int) (clone $today)->where('state', SandboxState::CAPTURED->value)->count(),
            'failed_today' => (int) (clone $today)->where('state', SandboxState::FAILED->value)->count(),
            'disputed_total' => (int) (clone $base)->where('state', SandboxState::DISPUTED->value)->count(),
            'pending_webhooks' => (int) SandboxWebhookOutbox::query()
                ->where('sandbox_merchant_id', $merchant->id)
                ->where('status', 'queued')
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentTransactions(SandboxMerchant $merchant): array
    {
        return SandboxTransaction::query()
            ->where('sandbox_merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SandboxTransaction $t) => [
                'uuid' => $t->uuid,
                'reference' => $t->reference,
                'emulate' => $t->emulate,
                'method' => $t->method,
                'state' => $t->state,
                'scenario' => $t->scenario,
                'reason_code' => $t->reason_code,
                'amount' => number_format($t->amount_minor / 100, 2),
                'currency' => $t->currency,
                'created_at' => $t->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function recentWebhooks(SandboxMerchant $merchant): array
    {
        return SandboxWebhookOutbox::query()
            ->where('sandbox_merchant_id', $merchant->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (SandboxWebhookOutbox $w) => [
                'uuid' => $w->uuid,
                'event_id' => $w->event_id,
                'type' => $w->type,
                'emulate' => $w->emulate,
                'status' => $w->status,
                'attempts' => $w->attempts,
                'scheduled_for' => $w->scheduled_for?->toIso8601String(),
                'response_status' => $w->response_status,
            ])
            ->all();
    }
}
