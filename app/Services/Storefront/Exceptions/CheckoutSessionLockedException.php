<?php

declare(strict_types=1);

namespace App\Services\Storefront\Exceptions;

use RuntimeException;

class CheckoutSessionLockedException extends RuntimeException
{
    public function __construct(string $reason = 'This checkout session can no longer be modified.')
    {
        parent::__construct($reason);
    }
}
