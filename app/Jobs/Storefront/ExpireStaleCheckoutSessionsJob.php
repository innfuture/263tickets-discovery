<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Enums\CheckoutSessionStatus;
use App\Models\CheckoutSession;
use App\Models\Organization;
use App\Services\Automation\AutomationDispatcher;
use App\Services\Storefront\CheckoutSessionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

/**
 * Periodic sweep — every open/paying session whose `expires_at` has
 * passed (plus a small grace window for stragglers) is expired and
 * its held items released.
 *
 * Schedule from routes/console.php at one-minute cadence. The job
 * processes in chunks so a backlog after an outage doesn't OOM.
 */
class ExpireStaleCheckoutSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(CheckoutSessionManager $manager, AutomationDispatcher $dispatcher): void
    {
        $grace = (int) config('storefront.checkout.expiry_grace_seconds', 30);
        $cutoff = Carbon::now()->subSeconds($grace);

        CheckoutSession::query()
            ->whereIn('status', [
                CheckoutSessionStatus::Open->value,
                CheckoutSessionStatus::Paying->value,
            ])
            ->where('expires_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(200, function ($sessions) use ($manager, $dispatcher) {
                foreach ($sessions as $session) {
                    $this->emitAbandoned($session, $dispatcher);
                    $manager->expire($session);
                }
            });
    }

    /**
     * Fire `checkout.abandoned` for any expired session where the
     * buyer left enough behind to be re-targeted — at minimum, an
     * email. Anonymous expiries don't get an automation event since
     * there's no actor to reach out to.
     */
    protected function emitAbandoned(CheckoutSession $session, AutomationDispatcher $dispatcher): void
    {
        if (empty($session->buyer_email)) {
            return;
        }

        $org = Organization::query()->where('uuid', $session->organisation_id)->first();
        if (! $org) {
            return;
        }

        $session->loadMissing('items.category', 'event');

        $dispatcher->dispatch('checkout.abandoned', $org, [
            'session_uuid' => $session->uuid,
            'buyer' => [
                'name' => $session->buyer_name,
                'email' => $session->buyer_email,
                'phone' => $session->buyer_phone,
                'country_code' => $session->buyer_country_code,
            ],
            'event' => $session->event ? [
                'slug' => $session->event->slug,
                'name' => $session->event->name,
                'starts_at' => optional($session->event->starts_at)->toIso8601String(),
            ] : null,
            'totals' => [
                'subtotal_cents' => (int) $session->subtotal_cents,
                'total_cents' => (int) $session->total_cents,
                'currency' => $session->currency,
            ],
            'items' => $session->items->map(fn ($i) => [
                'ticket_type' => $i->category?->name,
                'quantity' => (int) $i->quantity,
            ])->all(),
            'attribution' => [
                'utm_source' => $session->utm_source,
                'utm_medium' => $session->utm_medium,
                'utm_campaign' => $session->utm_campaign,
            ],
        ]);
    }
}
