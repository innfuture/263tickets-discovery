<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use App\Models\OrderItem;
use App\Services\Storefront\Contracts\PassGenerator;
use RuntimeException;

/**
 * Google Wallet "Save to Google Pay" — we return a signed JWT the
 * front-end pastes into `https://pay.google.com/gp/v/save/<jwt>`.
 *
 * The wallet object itself must already exist (or be auto-created
 * via the `eventTicketObjects` payload inside the JWT). Requires a
 * GCP service account with the `wallet_object.issuer` scope; configure
 * via `STOREFRONT_GOOGLE_WALLET_SERVICE_ACCOUNT_PATH` and
 * `STOREFRONT_GOOGLE_WALLET_ISSUER_ID`.
 *
 * This class returns the JWT bytes as the "pass" — `contentType` is
 * `application/jwt` and the front-end is expected to wrap it in the
 * pay.google.com URL. The wrapper service can also expose a helper
 * that builds the full URL directly.
 */
class GoogleWalletPassGenerator implements PassGenerator
{
    public function __construct(
        protected string $serviceAccountPath,
        protected string $issuerId,
        protected string $classId,
    ) {}

    public function identifier(): string
    {
        return 'google';
    }

    public function contentType(): string
    {
        return 'application/jwt';
    }

    public function filename(OrderItem $item): string
    {
        return 'ticket-'.$item->order->reference.'-'.$item->id.'.jwt';
    }

    public function build(OrderItem $item): string
    {
        if (! is_file($this->serviceAccountPath)) {
            throw new RuntimeException('Google Wallet service account not configured.');
        }

        $sa = json_decode((string) file_get_contents($this->serviceAccountPath), true);
        if (! is_array($sa) || ! isset($sa['client_email'], $sa['private_key'])) {
            throw new RuntimeException('Invalid Google service account JSON.');
        }

        $event = $item->order->event;
        $objectId = $this->issuerId.'.'.$item->order->reference.'-'.$item->id;

        $payload = [
            'iss' => $sa['client_email'],
            'aud' => 'google',
            'typ' => 'savetowallet',
            'iat' => time(),
            'origins' => [(string) config('storefront.public_url')],
            'payload' => [
                'eventTicketObjects' => [[
                    'id' => $objectId,
                    'classId' => $this->classId,
                    'state' => 'ACTIVE',
                    'ticketHolderName' => (string) $item->attendee_name,
                    'ticketNumber' => (string) ($item->order->reference.'-'.$item->id),
                    'barcode' => [
                        'type' => 'QR_CODE',
                        'value' => (string) $item->qr_payload,
                    ],
                ]],
            ],
        ];

        return $this->signRs256($payload, (string) $sa['private_key']);
    }

    /** @param array<string, mixed> $payload */
    protected function signRs256(array $payload, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $encode = fn (array $p) => $this->b64url(json_encode($p, JSON_UNESCAPED_SLASHES));
        $signingInput = $encode($header).'.'.$encode($payload);

        $signature = '';
        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return $signingInput.'.'.$this->b64url($signature);
    }

    protected function b64url(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }
}
