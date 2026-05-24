<?php

declare(strict_types=1);

namespace App\Services\Storefront\Exceptions;

use RuntimeException;

class CurrencyMismatchException extends RuntimeException
{
    public function __construct(
        public readonly int $ticketCategoryId,
        public readonly string $cartCurrency,
    ) {
        parent::__construct("This ticket tier is not available in {$cartCurrency}.");
    }
}
