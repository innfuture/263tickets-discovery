<?php

declare(strict_types=1);

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\Payments\ProcessWebhookEventJob;
use App\Models\PaymentWebhookEvent;
use App\Services\Payments\Contracts\HandlesWebhooks;
use App\Services\Payments\Exceptions\PaymentException;
use App\Services\Payments\Exceptions\WebhookSignatureException;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Inbound webhook router. Routes are CSRF-exempt (registered in
 * bootstrap/app.php) and the path looks like /payments/webhooks/{gateway}.
 *
 * Flow:
 *
 *   1. Resolve driver by URL segment.
 *   2. Require the driver implements HandlesWebhooks (otherwise 404).
 *   3. Verify signature — driver throws WebhookSignatureException on
 *      mismatch and we 401, logging the attempt.
 *   4. Parse the payload into a normalised WebhookEvent.
 *   5. Dedupe by sha256 of the raw body. Duplicates short-circuit to
 *      200 OK so providers stop retrying.
 *   6. Persist a PaymentWebhookEvent row and dispatch
 *      ProcessWebhookEventJob to apply side effects asynchronously.
 *
 * Always responds 200 once accepted — drivers retry on non-2xx, and
 * we want them to stop retrying as soon as we've stored the payload.
 */
class WebhookController extends Controller
{
    public function __construct(protected PaymentManager $payments) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        try {
            $driver = $this->payments->gateway($gateway);
        } catch (PaymentException) {
            return response()->json(['error' => 'unknown_gateway'], 404);
        }

        if (! $driver instanceof HandlesWebhooks) {
            return response()->json(['error' => 'gateway_does_not_handle_webhooks'], 404);
        }

        $rawBody = $request->getContent();
        $signatureHash = hash('sha256', $rawBody);

        $duplicate = PaymentWebhookEvent::where('signature_hash', $signatureHash)
            ->where('gateway', $gateway)
            ->first();
        if ($duplicate !== null) {
            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            $driver->verifyWebhook($request);
        } catch (WebhookSignatureException $e) {
            Log::warning('payment.webhook.signature_failed', [
                'gateway' => $gateway,
                'message' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'invalid_signature'], 401);
        }

        $event = $driver->parseWebhook($request);
        if ($event === null) {
            return response()->json(['status' => 'ignored'], 200);
        }

        $row = PaymentWebhookEvent::create([
            'gateway' => $gateway,
            'event_type' => $event->eventType,
            'reference' => $event->reference,
            'gateway_reference' => $event->gatewayReference,
            'status' => $event->status->value,
            'signature_hash' => $signatureHash,
            'payload' => $event->raw,
            'headers' => $this->safeHeaders($request),
            'processing_status' => 'received',
        ]);

        ProcessWebhookEventJob::dispatch($row->id);

        return response()->json([
            'status' => 'accepted',
            'event_id' => $row->uuid,
        ], 200);
    }

    /**
     * Strip headers we don't want stored — Authorization and cookies
     * are sensitive; everything else helps with debugging.
     *
     * @return array<string, mixed>
     */
    protected function safeHeaders(Request $request): array
    {
        $headers = collect($request->headers->all())
            ->except(['authorization', 'cookie', 'x-api-key'])
            ->toArray();

        return $headers;
    }
}
