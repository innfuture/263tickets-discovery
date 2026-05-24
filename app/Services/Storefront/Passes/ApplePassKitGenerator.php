<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use App\Models\OrderItem;
use App\Services\Storefront\Contracts\PassGenerator;
use RuntimeException;
use ZipArchive;

/**
 * Builds an Apple Wallet `.pkpass` bundle. PassKit requires an Apple
 * Developer Pass Type ID cert and the Apple WWDR intermediate cert —
 * configure both via `STOREFRONT_APPLE_PASS_CERT_PATH` /
 * `STOREFRONT_APPLE_PASS_WWDR_PATH` / `STOREFRONT_APPLE_PASS_CERT_PASSPHRASE`
 * + `STOREFRONT_APPLE_PASS_TEAM_ID` / `STOREFRONT_APPLE_PASS_TYPE_ID`.
 *
 * The bundle layout is:
 *   pass.json
 *   manifest.json   (SHA-1 of each file)
 *   signature       (PKCS#7 detached signature of manifest.json)
 *   icon.png + icon@2x.png  (consumer must provide via storage)
 *
 * `signature` requires OpenSSL with `openssl_pkcs7_sign`. When the
 * cert files aren't present we throw — the caller (WalletPassService)
 * catches and falls back to StubPassGenerator so the buyer still gets
 * something downloadable.
 */
class ApplePassKitGenerator implements PassGenerator
{
    public function __construct(
        protected string $certPath,
        protected string $wwdrPath,
        protected string $passphrase,
        protected string $teamId,
        protected string $passTypeId,
        protected string $iconPath,
    ) {}

    public function identifier(): string
    {
        return 'apple';
    }

    public function contentType(): string
    {
        return 'application/vnd.apple.pkpass';
    }

    public function filename(OrderItem $item): string
    {
        return 'ticket-'.$item->order->reference.'-'.$item->id.'.pkpass';
    }

    public function build(OrderItem $item): string
    {
        if (! is_file($this->certPath) || ! is_file($this->wwdrPath) || ! is_file($this->iconPath)) {
            throw new RuntimeException('Apple PassKit credentials / icon not configured.');
        }

        $passJson = json_encode($this->passPayload($item), JSON_UNESCAPED_SLASHES);
        if ($passJson === false) {
            throw new RuntimeException('Failed to encode pass.json.');
        }

        $icon = (string) file_get_contents($this->iconPath);

        $files = [
            'pass.json' => $passJson,
            'icon.png' => $icon,
            'icon@2x.png' => $icon,
        ];

        $manifest = [];
        foreach ($files as $name => $content) {
            $manifest[$name] = sha1($content);
        }
        $manifestJson = json_encode($manifest, JSON_UNESCAPED_SLASHES);

        $signature = $this->sign((string) $manifestJson);

        return $this->zip($files + [
            'manifest.json' => (string) $manifestJson,
            'signature' => $signature,
        ]);
    }

    /** @return array<string, mixed> */
    protected function passPayload(OrderItem $item): array
    {
        $event = $item->order->event;

        return [
            'formatVersion' => 1,
            'passTypeIdentifier' => $this->passTypeId,
            'teamIdentifier' => $this->teamId,
            'organizationName' => optional($item->order->organization)->name ?? 'Event',
            'description' => 'Event ticket',
            'serialNumber' => $item->order->reference.'-'.$item->id,
            'barcode' => [
                'format' => 'PKBarcodeFormatQR',
                'message' => (string) $item->qr_payload,
                'messageEncoding' => 'iso-8859-1',
            ],
            'eventTicket' => [
                'primaryFields' => [[
                    'key' => 'event',
                    'label' => 'EVENT',
                    'value' => (string) ($event?->name ?? ''),
                ]],
                'secondaryFields' => [
                    ['key' => 'attendee', 'label' => 'NAME', 'value' => (string) $item->attendee_name],
                    ['key' => 'tier', 'label' => 'TYPE', 'value' => (string) $item->ticket_type],
                ],
                'auxiliaryFields' => [
                    ['key' => 'doors', 'label' => 'DOORS', 'value' => optional($event?->doors_open_at)->toIso8601String() ?? ''],
                ],
            ],
        ];
    }

    protected function sign(string $manifestJson): string
    {
        $tmpManifest = tempnam(sys_get_temp_dir(), 'manifest-');
        $tmpSignature = tempnam(sys_get_temp_dir(), 'signature-');
        file_put_contents($tmpManifest, $manifestJson);

        $certs = [];
        openssl_pkcs12_read((string) file_get_contents($this->certPath), $certs, $this->passphrase);

        openssl_pkcs7_sign(
            $tmpManifest,
            $tmpSignature,
            $certs['cert'],
            [$certs['pkey'], $this->passphrase],
            [],
            PKCS7_BINARY | PKCS7_DETACHED,
            $this->wwdrPath,
        );

        $signed = (string) file_get_contents($tmpSignature);
        @unlink($tmpManifest);
        @unlink($tmpSignature);

        // openssl_pkcs7_sign emits S/MIME — strip the headers + base64-decode.
        $parts = explode("\n\n", $signed, 2);
        $body = trim(end($parts));

        return (string) base64_decode($body, true);
    }

    /** @param array<string, string> $files */
    protected function zip(array $files): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkpass-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        $bytes = (string) file_get_contents($path);
        @unlink($path);

        return $bytes;
    }
}
