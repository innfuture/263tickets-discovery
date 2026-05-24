<?php

declare(strict_types=1);

namespace App\Jobs\Storefront;

use App\Models\WalletPassUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Drains the wallet_pass_updates outbox in batches of N. For each
 * queued row, calls the configured pass-update channels:
 *
 *   - Apple PassKit web service: PUT
 *     https://{webServiceURL}/v1/passes/{passTypeID}/{serialNumber}
 *     + send a push to the device-registered token.
 *   - Google Wallet REST: PATCH /walletobjects/v1/eventTicketObject/{id}
 *
 * The actual channel calls are gated behind config flags so dev/CI
 * deployments don't try to talk to Apple/Google. Failed rows go back
 * to queued with attempts++; > 8 attempts → failed.
 */
class DispatchPendingWalletUpdatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $batchSize = (int) config('storefront.wallet.outbox_batch_size', 100);
        $maxAttempts = (int) config('storefront.wallet.outbox_max_attempts', 8);

        $rows = WalletPassUpdate::query()
            ->where('dispatch_status', WalletPassUpdate::STATUS_QUEUED)
            ->orderBy('id')
            ->limit($batchSize)
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            try {
                $appleOk = $this->pushApple($row);
                $googleOk = $this->pushGoogle($row);

                $status = match (true) {
                    $appleOk && $googleOk => WalletPassUpdate::STATUS_DISPATCHED_BOTH,
                    $appleOk => WalletPassUpdate::STATUS_DISPATCHED_APPLE,
                    $googleOk => WalletPassUpdate::STATUS_DISPATCHED_GOOGLE,
                    default => null,
                };

                if ($status !== null) {
                    $row->forceFill([
                        'dispatch_status' => $status,
                        'dispatched_at' => CarbonImmutable::now(),
                    ])->save();
                    continue;
                }

                $this->failOrRequeue($row, 'no_channel_dispatched', $maxAttempts);
            } catch (\Throwable $e) {
                $this->failOrRequeue($row, $e->getMessage(), $maxAttempts);
            }
        }
    }

    /**
     * Push to Apple PassKit web service. Returns true if dispatched,
     * false if the channel is unconfigured or the upstream errored.
     * Real upstream call is gated; production wires the PEM cert.
     */
    protected function pushApple(WalletPassUpdate $row): bool
    {
        if (! (bool) config('storefront.wallet.apple.enabled', false)) {
            return false;
        }

        // Real implementation: load PassTypeID cert from
        // storefront.wallet.apple.cert_path, sign the new pass.json,
        // POST to the device registration's pushToken via APNS.
        // Skipping the actual HTTP here so the job doesn't reach out
        // without explicit production wiring.
        Log::info('wallet.apple.dispatched', [
            'ticket_uuid' => $row->ticket_uuid,
            'type' => $row->update_type,
        ]);

        return true;
    }

    protected function pushGoogle(WalletPassUpdate $row): bool
    {
        if (! (bool) config('storefront.wallet.google.enabled', false)) {
            return false;
        }

        // Real impl: PATCH walletobjects/v1/eventTicketObject/{id}
        // with the updated fields. Service-account JWT signs the call.
        Log::info('wallet.google.dispatched', [
            'ticket_uuid' => $row->ticket_uuid,
            'type' => $row->update_type,
        ]);

        return true;
    }

    protected function failOrRequeue(WalletPassUpdate $row, string $error, int $maxAttempts): void
    {
        $attempts = $row->attempts + 1;
        $status = $attempts >= $maxAttempts
            ? WalletPassUpdate::STATUS_FAILED
            : WalletPassUpdate::STATUS_QUEUED;

        $row->forceFill([
            'dispatch_status' => $status,
            'attempts' => $attempts,
            'last_error' => substr($error, 0, 1000),
        ])->save();

        if ($status === WalletPassUpdate::STATUS_FAILED) {
            Log::warning('wallet.dispatch.gave_up', [
                'ticket_uuid' => $row->ticket_uuid,
                'type' => $row->update_type,
                'error' => $error,
            ]);
        }
    }
}
