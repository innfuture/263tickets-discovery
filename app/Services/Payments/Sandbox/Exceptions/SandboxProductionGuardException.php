<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox\Exceptions;

use App\Services\Payments\Exceptions\PaymentException;

/**
 * Hard-stop when the kernel boots in `production` without an explicit
 * override. Two layers (driver constructor + route conditional) so a
 * single misconfig cannot leak the sandbox.
 */
class SandboxProductionGuardException extends PaymentException {}
