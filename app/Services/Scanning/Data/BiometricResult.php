<?php

declare(strict_types=1);

namespace App\Services\Scanning\Data;

final class BiometricResult
{
    public const STATE_PASS = 'pass';

    public const STATE_SOFT_FAIL = 'soft_fail';

    public const STATE_HARD_FAIL = 'hard_fail';

    public function __construct(
        public readonly string $state,
        public readonly float $confidence,
        public readonly ?string $message = null,
    ) {}

    public static function pass(float $confidence): self
    {
        return new self(self::STATE_PASS, $confidence);
    }

    public static function softFail(float $confidence, string $message): self
    {
        return new self(self::STATE_SOFT_FAIL, $confidence, $message);
    }

    public static function hardFail(string $message, float $confidence = 0.0): self
    {
        return new self(self::STATE_HARD_FAIL, $confidence, $message);
    }
}
