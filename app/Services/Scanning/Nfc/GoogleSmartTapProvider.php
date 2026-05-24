<?php

declare(strict_types=1);

namespace App\Services\Scanning\Nfc;

use App\Models\NfcVerification;
use App\Models\OfflineTicket;
use App\Services\Scanning\Nfc\Contracts\NfcVerificationProvider;
use App\Services\Scanning\Nfc\Data\NfcDecodedTap;
use App\Services\Scanning\Nfc\Exceptions\NfcVerificationException;
use Illuminate\Support\Facades\Log;

/**
 * Google Smart Tap — Google Wallet → reader handshake. Reader presents
 * a Collector Token; phone responds with a one-time payload signed by
 * a key chain rooted at the issuer's Smart Tap key pair.
 *
 * Same two-shape contract as AppleVasProvider — accept pre-decoded
 * serial or fall through to logging when handed a raw encrypted blob
 * (which requires the issuer's Smart Tap private key chain).
 */
class GoogleSmartTapProvider implements NfcVerificationProvider
{
    public function __construct(
        protected ?string $issuerId,
        protected ?string $smartTapKeyPath,
    ) {}

    public function identifier(): string
    {
        return NfcVerification::PROVIDER_GOOGLE_SMART_TAP;
    }

    public function decode(string $encodedPayload, array $context = []): NfcDecodedTap
    {
        if (! $this->configured()) {
            throw new NfcVerificationException(
                'not_configured',
                'Google Smart Tap credentials are not configured.',
            );
        }

        $payload = trim($encodedPayload);

        if (str_starts_with($payload, 'objectId:')) {
            $objectId = substr($payload, strlen('objectId:'));
        } elseif (preg_match('/^[A-Za-z0-9._:-]+$/', $payload)) {
            $objectId = $payload;
        } else {
            Log::warning('google_smart_tap.encrypted_blob_received', [
                'issuer_id' => $this->issuerId,
                'note' => 'wire openssl + Smart Tap key chain to handle this path',
            ]);

            throw new NfcVerificationException(
                'encrypted_blob_unsupported',
                'Encrypted Smart Tap blob received but decrypt path is not wired. Use a reader SDK that pre-decrypts to object id.',
            );
        }

        // Google Wallet object IDs are typically `<issuerId>.<orderRef-itemId>`.
        // The pass generator (GoogleWalletPassGenerator) writes this
        // format; we reverse it to find the OrderItem and from there
        // the OfflineTicket.
        $tail = explode('.', $objectId, 2)[1] ?? '';
        $parts = explode('-', $tail);
        $orderRef = $parts[0] ?? '';
        $itemId = (int) ($parts[1] ?? 0);

        $ticket = null;
        if ($orderRef !== '' && $itemId > 0) {
            $ticket = OfflineTicket::query()
                ->whereHas('event.orders', fn ($q) => $q->where('reference', $orderRef))
                ->whereExists(function ($q) use ($itemId) {
                    $q->select(\DB::raw(1))
                        ->from('order_items')
                        ->whereColumn('order_items.offline_ticket_id', 'offline_tickets.id')
                        ->where('order_items.id', $itemId);
                })
                ->first();
        }

        if (! $ticket) {
            throw new NfcVerificationException('ticket_not_found', "No ticket for object {$objectId}.");
        }

        return new NfcDecodedTap(
            ticketUuid: (string) $ticket->uuid,
            qrEquivalentPayload: (string) $ticket->qr_payload,
            provider: $this->identifier(),
            payloadHash: hash('sha256', $payload.'|'.($this->issuerId ?? '')),
            metadata: ['object_id' => $objectId, 'issuer_id' => $this->issuerId],
        );
    }

    protected function configured(): bool
    {
        return ! empty($this->issuerId) && ! empty($this->smartTapKeyPath);
    }
}
