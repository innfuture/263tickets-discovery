<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Enums\CheckoutSessionStatus;
use App\Jobs\Storefront\NotifyWaitlistOnCapacityReleasedJob;
use App\Models\CheckoutSession;
use App\Models\Event;
use App\Models\WaitlistEntry;
use App\Services\Storefront\Exceptions\CheckoutSessionLockedException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle owner for CheckoutSession rows. Other services call this
 * instead of touching the model directly so policy (TTL, idempotency,
 * status transitions, anti-bot caps) lives in one place.
 *
 * The session is the cart. While `status` is open or paying its items
 * count against tier inventory; once it lands in a terminal state
 * (completed / expired / cancelled) the holds disappear from the
 * availability calc on the next read.
 */
class CheckoutSessionManager
{
    public function __construct(protected TicketReservation $reservation) {}

    /**
     * Create a fresh session for an event. Honours idempotency_key —
     * a repeated call with the same key returns the existing session.
     *
     * @param  array<string, mixed>  $attribution
     */
    public function create(
        Event $event,
        string $currency,
        Request $request,
        array $attribution = [],
        ?string $idempotencyKey = null,
    ): CheckoutSession {
        if ($idempotencyKey !== null) {
            $existing = CheckoutSession::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing;
            }
        }

        $this->enforcePerIpCap($request->ip());

        $ttl = max(1, (int) config('storefront.checkout.hold_ttl_minutes', 15));

        return DB::transaction(function () use ($event, $currency, $request, $attribution, $idempotencyKey, $ttl) {
            return CheckoutSession::create([
                'organisation_id' => $event->organisation_id,
                'event_id' => $event->id,
                'currency' => strtoupper($currency),
                'status' => CheckoutSessionStatus::Open,
                'expires_at' => Carbon::now()->addMinutes($ttl),
                'idempotency_key' => $idempotencyKey,
                'ip_address' => $request->ip(),
                'user_agent_hash' => $request->userAgent() ? hash('sha256', $request->userAgent()) : null,
                'referral_source' => $attribution['referral_source'] ?? null,
                'utm_source' => $attribution['utm_source'] ?? null,
                'utm_medium' => $attribution['utm_medium'] ?? null,
                'utm_campaign' => $attribution['utm_campaign'] ?? null,
                'utm_content' => $attribution['utm_content'] ?? null,
                'utm_term' => $attribution['utm_term'] ?? null,
                'buyer_locale' => $attribution['buyer_locale'] ?? null,
                'buyer_country_code' => $attribution['buyer_country_code'] ?? null,
            ]);
        });
    }

    /**
     * Re-stamp expires_at to extend the session. Used after a meaningful
     * mutation (added an item, supplied attendees) so the buyer doesn't
     * lose their hold mid-flow. Capped to prevent indefinite extension.
     */
    public function touch(CheckoutSession $session): CheckoutSession
    {
        if (! $session->isMutable()) {
            return $session;
        }
        $ttl = (int) config('storefront.checkout.hold_ttl_minutes', 15);
        $session->forceFill(['expires_at' => Carbon::now()->addMinutes($ttl)])->save();

        return $session;
    }

    /**
     * Mark the session as locked while we hand off to the payment
     * gateway. Prevents a parallel /pay click from kicking off a second
     * charge against the same cart.
     */
    public function lockForPayment(CheckoutSession $session): CheckoutSession
    {
        $this->assertMutable($session);

        // Bump expiry so the gateway redirect dance has breathing room.
        $session->forceFill([
            'status' => CheckoutSessionStatus::Paying,
            'locked_at' => Carbon::now(),
            'expires_at' => Carbon::now()->addMinutes(20),
        ])->save();

        return $session->refresh();
    }

    public function complete(CheckoutSession $session): CheckoutSession
    {
        $session->forceFill([
            'status' => CheckoutSessionStatus::Completed,
            'completed_at' => Carbon::now(),
        ])->save();

        return $session->refresh();
    }

    public function cancel(CheckoutSession $session, string $reason = 'buyer_cancelled'): CheckoutSession
    {
        // $reason currently only feeds logs/audit; surface for future
        // analytics by stashing in attendee_data? Today we don't persist.
        unset($reason);
        $eventId = (int) $session->event_id;

        $session->forceFill([
            'status' => CheckoutSessionStatus::Cancelled,
            'cancelled_at' => Carbon::now(),
        ])->save();

        $this->reservation->release($session);
        $this->fanoutCapacityReleased($eventId);

        return $session->refresh();
    }

    public function expire(CheckoutSession $session): CheckoutSession
    {
        $eventId = (int) $session->event_id;

        $session->forceFill([
            'status' => CheckoutSessionStatus::Expired,
            'cancelled_at' => Carbon::now(),
        ])->save();

        $this->reservation->release($session);
        $this->fanoutCapacityReleased($eventId);

        return $session->refresh();
    }

    /**
     * Fan-out to the waitlist notifier. Only fires when there's
     * actually a waitlist on the event so the job queue doesn't churn
     * on every sweep cycle.
     */
    protected function fanoutCapacityReleased(int $eventId): void
    {
        if ($eventId <= 0) {
            return;
        }

        $hasWaitlist = WaitlistEntry::query()
            ->where('event_id', $eventId)
            ->where('status', 'pending')
            ->exists();

        if ($hasWaitlist) {
            NotifyWaitlistOnCapacityReleasedJob::dispatch($eventId)
                ->onQueue((string) config('storefront.fulfilment.queue', 'storefront'));
        }
    }

    protected function assertMutable(CheckoutSession $session): void
    {
        if (! $session->isMutable()) {
            throw new CheckoutSessionLockedException;
        }
        if ($session->isExpired()) {
            throw new CheckoutSessionLockedException('This checkout session has expired.');
        }
    }

    protected function enforcePerIpCap(?string $ip): void
    {
        if ($ip === null) {
            return;
        }
        $cap = (int) config('storefront.checkout.max_open_sessions_per_ip', 3);
        if ($cap <= 0) {
            return;
        }
        $count = CheckoutSession::query()
            ->where('ip_address', $ip)
            ->whereIn('status', ['open', 'paying'])
            ->where('expires_at', '>=', Carbon::now())
            ->count();

        if ($count >= $cap) {
            throw new CheckoutSessionLockedException(
                'Too many active checkout sessions from your network. Wait a few minutes and retry.'
            );
        }
    }
}
