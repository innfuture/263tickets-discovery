<?php

declare(strict_types=1);

namespace App\Services\Payments\Contracts;

use App\Services\Payments\Data\WebhookEvent;
use Illuminate\Http\Request;

interface HandlesWebhooks
{
    /**
     * Validate signature / shared secret. Drivers that cannot verify
     * (some providers post in cleartext) must still implement source-
     * IP allow-listing here or return false.
     *
     * Throwing WebhookSignatureException is preferred over returning
     * false when the failure mode is interesting enough to alert on.
     */
    public function verifyWebhook(Request $request): bool;

    /**
     * Normalise the provider's payload to a WebhookEvent. Returning
     * null tells the controller this was a noise event (heartbeat,
     * unknown type) and should be acknowledged without side effects.
     */
    public function parseWebhook(Request $request): ?WebhookEvent;
}
