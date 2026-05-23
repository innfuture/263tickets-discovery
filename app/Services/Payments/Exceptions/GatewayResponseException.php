<?php

declare(strict_types=1);

namespace App\Services\Payments\Exceptions;

/**
 * Thrown when the upstream gateway returns a structurally valid but
 * unsuccessful response (rejected charge, declined card, validation
 * error). The original payload is attached for the audit log.
 */
class GatewayResponseException extends PaymentException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(
        string $message,
        public readonly array $response = [],
        public readonly ?string $gatewayCode = null,
    ) {
        parent::__construct($message);
    }
}
