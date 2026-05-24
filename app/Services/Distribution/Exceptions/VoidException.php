<?php

declare(strict_types=1);

namespace App\Services\Distribution\Exceptions;

use RuntimeException;

class VoidException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
