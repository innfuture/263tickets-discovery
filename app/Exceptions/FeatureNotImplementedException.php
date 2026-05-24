<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a code path is reached that depends on an integration
 * the deployment has not opted into (e.g. AdManagementService when
 * config('ads.enabled') is false). Distinct exception type so callers
 * can render a meaningful "this integration is not enabled" message
 * instead of leaking implementation detail.
 */
class FeatureNotImplementedException extends RuntimeException
{
}
