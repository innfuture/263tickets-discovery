<?php

declare(strict_types=1);

namespace App\Services\Distribution;

/**
 * SHA-256 binary Merkle tree over an ordered list of leaves. Used to
 * commit a dispatch manifest (the list of ticket UUIDs being shipped)
 * to a single 32-byte root that the issuer signs and the recipient
 * verifies.
 *
 * Subset transfers: a Parent dispatching part of a previously-received
 * batch to a Child carries each leaf's inclusion proof so the Child
 * can verify the leaf was part of the Parent's signed root before
 * accepting custody.
 *
 * Odd levels are handled by doubling the last leaf (the conventional
 * scheme used by Bitcoin et al. — note that this introduces a CVE-
 * style ambiguity if leaves can repeat; we sort + dedupe leaves to
 * sidestep it).
 */
class MerkleTree
{
    /**
     * Build a root + per-leaf paths from the supplied UUIDs. Returns:
     *   ['root' => hex, 'paths' => [uuid => [hex_sibling, ...], ...]]
     *
     * @param  array<int, string>  $uuids
     * @return array{root: string, paths: array<string, array<int, array{hash: string, side: string}>>}
     */
    public function build(array $uuids): array
    {
        if ($uuids === []) {
            return ['root' => str_repeat('0', 64), 'paths' => []];
        }

        $sorted = array_values(array_unique($uuids));
        sort($sorted);

        $leaves = array_map(fn (string $u): string => $this->leafHash($u), $sorted);
        $paths = array_fill_keys($sorted, []);

        $level = $leaves;
        $indexByUuid = array_flip($sorted);
        $positions = $indexByUuid;

        while (count($level) > 1) {
            $next = [];
            $nextPositions = [];

            for ($i = 0; $i < count($level); $i += 2) {
                $left = $level[$i];
                $right = $level[$i + 1] ?? $left; // duplicate odd tail
                $parent = hash('sha256', hex2bin($left).hex2bin($right));

                foreach ($positions as $uuid => $pos) {
                    if ($pos === $i) {
                        $paths[$uuid][] = ['hash' => $right, 'side' => 'right'];
                        $nextPositions[$uuid] = intdiv($i, 2);
                    } elseif ($pos === $i + 1) {
                        $paths[$uuid][] = ['hash' => $left, 'side' => 'left'];
                        $nextPositions[$uuid] = intdiv($i, 2);
                    }
                }
                $next[] = $parent;
            }

            $level = $next;
            $positions = $nextPositions;
        }

        return ['root' => $level[0], 'paths' => $paths];
    }

    /**
     * Verify a single UUID's inclusion in the given root using the
     * supplied path. Constant-time at the byte level via hash_equals.
     *
     * @param  array<int, array{hash: string, side: string}>  $path
     */
    public function verify(string $uuid, array $path, string $root): bool
    {
        $current = $this->leafHash($uuid);

        foreach ($path as $step) {
            $siblingHex = (string) ($step['hash'] ?? '');
            $side = (string) ($step['side'] ?? '');

            if (! preg_match('/^[0-9a-f]{64}$/', $siblingHex)) {
                return false;
            }

            $current = match ($side) {
                'right' => hash('sha256', hex2bin($current).hex2bin($siblingHex)),
                'left' => hash('sha256', hex2bin($siblingHex).hex2bin($current)),
                default => null,
            };

            if ($current === null) {
                return false;
            }
        }

        return hash_equals($root, $current);
    }

    public function leafHash(string $uuid): string
    {
        // Domain-separate leaves from internal nodes to prevent
        // second-preimage attacks (CVE-2012-2459 style).
        return hash('sha256', "leaf:".$uuid);
    }
}
