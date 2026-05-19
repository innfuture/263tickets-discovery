<?php

namespace App\Exceptions;

use App\Enums\EventStatus;
use DomainException;

class InvalidEventStatusTransition extends DomainException
{
    public function __construct(
        public readonly EventStatus $from,
        public readonly EventStatus $to,
    ) {
        parent::__construct("Cannot transition event from {$from->value} to {$to->value}.");
    }
}
