<?php

declare(strict_types=1);

namespace App\Services\Extensions;

use App\Models\ExtensionDeveloper;
use RuntimeException;

/**
 * RSA-SHA256 over the canonical-JSON encoding of the manifest. The
 * developer signs with their private key; we verify with the public
 * key on `extension_developers.public_key`.
 *
 * Why RSA-2048: hardware-token-friendly, every language has stdlib
 * support, signature is base64 ~340 bytes. The fingerprint is the
 * sha256 of the DER-encoded public key.
 */
class ExtensionSigner
{
    public function __construct(protected ExtensionManifestValidator $validator) {}

    /**
     * Verify the developer's signature over the manifest. Returns
     * true on match. Throws on malformed key / signature.
     *
     * @param  array<string, mixed>  $manifest
     */
    public function verify(ExtensionDeveloper $developer, array $manifest, string $signatureBase64): bool
    {
        $canonical = $this->validator->canonicalize($manifest);
        $signature = (string) base64_decode($signatureBase64, true);
        if ($signature === '') {
            throw new RuntimeException('Signature is not valid base64.');
        }

        $key = @openssl_pkey_get_public($developer->public_key);
        if ($key === false) {
            throw new RuntimeException('Developer public key is not a valid PEM.');
        }

        $verdict = openssl_verify($canonical, $signature, $key, OPENSSL_ALGO_SHA256);

        return $verdict === 1;
    }

    /**
     * sha256 of the DER-encoded public key — used for identifying
     * developers without exposing the full PEM.
     */
    public function fingerprint(string $pem): string
    {
        $key = @openssl_pkey_get_public($pem);
        if ($key === false) {
            throw new RuntimeException('Invalid public key.');
        }
        $details = openssl_pkey_get_details($key);
        if (! is_array($details) || ! isset($details['key'])) {
            throw new RuntimeException('Could not extract public key details.');
        }

        // openssl_pkey_get_details returns the PEM in `key`; sha256 of
        // it is stable across roundtrips.
        return hash('sha256', (string) $details['key']);
    }
}
