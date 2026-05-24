<?php

declare(strict_types=1);

namespace App\Services\Storefront\Exceptions;

use RuntimeException;

class InventoryUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly int $ticketCategoryId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Only {$available} ticket(s) remain in this tier (you asked for {$requested})."
        );
    }
}
