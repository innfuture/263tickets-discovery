<?php

declare(strict_types=1);

namespace App\Services\Distribution\Exceptions;

use RuntimeException;

/**
 * Raised when a dispatch can't be issued or received — bad inputs,
 * inventory not in the right state, signature mismatch on receipt,
 * Merkle root collision, recipient suspended, etc.
 */
class DispatchException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }
}
