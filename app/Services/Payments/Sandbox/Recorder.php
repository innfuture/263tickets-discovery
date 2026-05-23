<?php

declare(strict_types=1);

namespace App\Services\Payments\Sandbox;

use App\Models\SandboxRecording;

/**
 * Capture-and-replay of production gateway responses (§4.3, phase 6).
 *
 * Use cases:
 *   1. Diff-vs-prod tab in the inspector — render a side-by-side of
 *      the sandbox's synthetic response against a previously-captured
 *      real one for the same scenario.
 *   2. Replay: optionally have the sandbox short-circuit a charge and
 *      return the recorded production body verbatim (deterministic
 *      regression fixtures).
 *
 * Lookup slug is `{emulate}/{operation}/{scenario}` — exact match.
 * Adding a recording for a slug that already exists overwrites by
 * default (single source of truth per scenario).
 */
class Recorder
{
    /**
     * @param  array<string, mixed>  $requestSnapshot
     * @param  array<string, mixed>|null  $responseHeaders
     * @param  array<string, mixed>  $responseBody
     */
    public function record(
        string $emulate,
        string $operation,
        ?string $scenario,
        int $responseStatus,
        array $responseBody,
        ?array $responseHeaders = null,
        array $requestSnapshot = [],
        string $source = 'manual',
        ?string $notes = null,
    ): SandboxRecording {
        return SandboxRecording::updateOrCreate(
            [
                'emulate' => $emulate,
                'operation' => $operation,
                'scenario' => $scenario,
            ],
            [
                'request_snapshot' => $this->sanitise($requestSnapshot),
                'response_status' => $responseStatus,
                'response_headers' => $responseHeaders,
                'response_body' => $responseBody,
                'source' => $source,
                'notes' => $notes,
            ],
        );
    }

    public function find(string $emulate, string $operation, ?string $scenario): ?SandboxRecording
    {
        return SandboxRecording::query()
            ->where('emulate', $emulate)
            ->where('operation', $operation)
            ->where('scenario', $scenario)
            ->first();
    }

    /**
     * Strip the obviously-sensitive keys before persistence. Card PANs,
     * full CVVs, raw bearer tokens — anything we'd never want sitting
     * in the recording table where engineers might browse it.
     *
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    protected function sanitise(array $snapshot): array
    {
        $denylist = [
            'card.number', 'pan', 'cvc', 'cvv',
            'Authorization', 'authorization', 'api_key',
            'paymentData', 'paymentMethodData', 'token',
        ];

        return $this->stripKeys($snapshot, $denylist);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, mixed>
     */
    protected function stripKeys(array $data, array $keys): array
    {
        foreach ($data as $k => $v) {
            if (in_array((string) $k, $keys, true)) {
                $data[$k] = '__redacted__';

                continue;
            }
            if (is_array($v)) {
                $data[$k] = $this->stripKeys($v, $keys);
            }
        }

        return $data;
    }

    /**
     * Compute a textual diff between a sandbox response and the
     * recorded production fixture. Returns null when no fixture exists.
     *
     * Output is a list of `path => [recorded, sandbox]` for keys that
     * differ — small enough for the dashboard's Diff-vs-prod tab to
     * render directly.
     *
     * @param  array<string, mixed>  $sandboxBody
     * @return array<string, array{recorded: mixed, sandbox: mixed}>|null
     */
    public function diff(string $emulate, string $operation, ?string $scenario, array $sandboxBody): ?array
    {
        $recording = $this->find($emulate, $operation, $scenario);
        if ($recording === null) {
            return null;
        }

        $flatRecorded = $this->flatten((array) $recording->response_body);
        $flatSandbox = $this->flatten($sandboxBody);

        $diff = [];
        foreach ($flatRecorded as $path => $value) {
            $other = $flatSandbox[$path] ?? null;
            if ($value !== $other) {
                $diff[$path] = ['recorded' => $value, 'sandbox' => $other];
            }
        }
        foreach ($flatSandbox as $path => $value) {
            if (! array_key_exists($path, $flatRecorded)) {
                $diff[$path] = ['recorded' => null, 'sandbox' => $value];
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];
        foreach ($data as $k => $v) {
            $key = $prefix === '' ? (string) $k : "{$prefix}.{$k}";
            if (is_array($v)) {
                $flat += $this->flatten($v, $key);
            } else {
                $flat[$key] = $v;
            }
        }

        return $flat;
    }
}
