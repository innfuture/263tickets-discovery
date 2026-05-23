<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

/**
 * Provider-shape contract validator (§Phase 6 — cross-service contract
 * suite). For each emulator, a tiny schema describes the wire fields a
 * faithful sandbox response must produce. The contract suite runs
 * actual sandbox charges through this validator to catch drift.
 *
 * Two-layer:
 *   1. Required keys present and non-null.
 *   2. Optional keys present in the response are well-typed.
 *
 * Schemas live in code rather than JSON because they describe behavior
 * (PHP types, conditional presence) better than JSON Schema can.
 */
class ContractValidator
{
    /**
     * Validate a sandbox response body against the named provider's
     * contract for the given operation. Returns a list of violations;
     * an empty list means the response is contract-clean.
     *
     * @param  array<string, mixed>  $responseBody
     * @return array<int, string>
     */
    public function validate(string $emulate, string $operation, array $responseBody): array
    {
        $contract = $this->contractFor($emulate, $operation);
        if ($contract === null) {
            return ["no contract defined for {$emulate}/{$operation}"];
        }

        $violations = [];

        foreach ($contract['required'] ?? [] as $path => $expectedType) {
            $value = data_get($responseBody, $path);
            if ($value === null) {
                $violations[] = "missing required field: {$path}";

                continue;
            }
            if ($expectedType !== null && ! $this->typeMatches($value, $expectedType)) {
                $violations[] = sprintf(
                    'field %s expected type %s, got %s',
                    $path, $expectedType, gettype($value),
                );
            }
        }

        foreach ($contract['optional'] ?? [] as $path => $expectedType) {
            $value = data_get($responseBody, $path);
            if ($value !== null && ! $this->typeMatches($value, $expectedType)) {
                $violations[] = sprintf(
                    'optional field %s present but wrong type (expected %s, got %s)',
                    $path, $expectedType, gettype($value),
                );
            }
        }

        return $violations;
    }

    /**
     * @return array{required?: array<string, string|null>, optional?: array<string, string>}|null
     */
    protected function contractFor(string $emulate, string $operation): ?array
    {
        return match ($emulate.':'.$operation) {
            'stripe:charge' => [
                'required' => [
                    'simulated' => 'bool',
                    'gateway' => 'string',
                    'state' => 'string',
                ],
                'optional' => [
                    'reason_code' => 'string',
                    'redirect_url' => 'string',
                    'instructions' => 'string',
                    'webhook_events' => 'array',
                ],
            ],

            'adyen:charge' => [
                'required' => [
                    'simulated' => 'bool',
                    'gateway' => 'string',
                    'state' => 'string',
                ],
                'optional' => [
                    'reason_code' => 'string',
                    'webhook_events' => 'array',
                ],
            ],

            'paynow:charge' => [
                'required' => [
                    'simulated' => 'bool',
                    'gateway' => 'string',
                    'state' => 'string',
                ],
                'optional' => [
                    'redirect_url' => 'string',
                    'webhook_events' => 'array',
                ],
            ],

            'ecocash:charge' => [
                'required' => [
                    'simulated' => 'bool',
                    'gateway' => 'string',
                    'state' => 'string',
                ],
                'optional' => [
                    'instructions' => 'string',
                    'webhook_events' => 'array',
                ],
            ],

            'pesepay:charge', 'zimswitch:charge' => [
                'required' => [
                    'simulated' => 'bool',
                    'gateway' => 'string',
                    'state' => 'string',
                ],
                'optional' => [
                    'redirect_url' => 'string',
                    'webhook_events' => 'array',
                ],
            ],

            default => null,
        };
    }

    protected function typeMatches(mixed $value, string $expected): bool
    {
        return match ($expected) {
            'bool', 'boolean' => is_bool($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value),
            'string' => is_string($value),
            'array' => is_array($value),
            default => true,
        };
    }
}
