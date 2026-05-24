<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\OfflineTicket;
use App\Services\Cloudflare\CloudflareKv;
use Illuminate\Support\Facades\Container;

/**
 * Bridges OfflineTicket void events into the Cloudflare KV cache the
 * `scanner-edge` Worker reads from. Lets the edge return
 * `verdict=deny` instantly without falling through to origin.
 *
 * Fires on:
 *   - is_voided flipping false → true  (PUT key)
 *   - is_voided flipping true → false  (DELETE key — rare, used by
 *     transfer-revoke and admin "un-void" flows)
 *
 * No-ops when Cloudflare isn't configured so dev / sandbox runs
 * don't depend on the cloud round-trip.
 */
class OfflineTicketVoidObserver
{
    public function saved(OfflineTicket $ticket): void
    {
        if (! $ticket->wasChanged('is_voided')) {
            return;
        }

        $namespaceId = (string) config('storefront.cloudflare.voided_namespace_id', '');
        if ($namespaceId === '') {
            return;
        }

        $kv = Container::getInstance()->make(CloudflareKv::class);

        if ((bool) $ticket->is_voided) {
            $kv->put(
                namespaceId: $namespaceId,
                key: (string) $ticket->uuid,
                value: (string) ($ticket->void_reason ?? 'voided'),
            );

            return;
        }

        $kv->delete($namespaceId, (string) $ticket->uuid);
    }
}
