<?php

declare(strict_types=1);

namespace App\Services\Storefront\Exceptions;

use RuntimeException;

class PromoCodeInvalidException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        string $publicMessage,
    ) {
        parent::__construct($publicMessage);
    }
}
