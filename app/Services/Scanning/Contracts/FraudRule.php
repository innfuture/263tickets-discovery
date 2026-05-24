<?php

declare(strict_types=1);

namespace App\Services\Scanning\Contracts;

use App\Services\Scanning\Data\FraudVerdict;
use App\Services\Scanning\Data\ScanContext;

/**
 * The strategy contract. A FraudRule inspects a ScanContext and
 * returns one FraudVerdict. The FraudEngine combines verdicts from
 * every registered rule and picks the strongest (highest severity).
 *
 * Rules MUST be pure functions of the ScanContext (read-only) and
 * MUST return promptly — they run on the hot path of every scan.
 *
 * Add a new rule by:
 *   1. Implementing this interface.
 *   2. Listing it in config/scanning.php under `fraud_rules`.
 * The FraudEngine pipeline picks it up automatically — no engine
 * edits required.
 */
interface FraudRule
{
    /**
     * Stable identifier — appears in `scan_events.fraud_flags[*].rule`
     * and in webhook payloads. Use snake_case.
     */
    public function id(): string;

    public function evaluate(ScanContext $context): FraudVerdict;
}
