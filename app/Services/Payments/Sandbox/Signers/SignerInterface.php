<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Signers;

use App\Models\SandboxMerchant;
use App\Models\SandboxWebhookOutbox;

interface SignerInterface
{
    /**
     * Produce the canonical body that will be signed AND POSTed. Most
     * signers just JSON-encode the payload; Paynow-style emits form-
     * encoded with a hash field.
     */
    public function body(SandboxWebhookOutbox $event): string;

    /**
     * Headers to merge onto the outbound request, including any
     * signature header(s) the receiving end expects.
     *
     * @return array<string, string>
     */
    public function headers(SandboxWebhookOutbox $event, SandboxMerchant $merchant): array;
}
