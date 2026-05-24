<?php

declare(strict_types=1);

namespace App\Services\Storefront\Exceptions;

use RuntimeException;

class SeatUnavailableException extends RuntimeException
{
    public function __construct(
        public readonly string $seatUuid,
        public readonly string $reason = 'already_held',
    ) {
        parent::__construct("Seat {$seatUuid} is no longer available ({$reason}).");
    }
}
