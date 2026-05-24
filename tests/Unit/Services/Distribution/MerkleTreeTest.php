<?php

declare(strict_types=1);

use App\Services\Distribution\MerkleTree;

it('produces a stable root for the same input regardless of order', function () {
    $tree = new MerkleTree();
    $a = $tree->build(['c', 'a', 'b', 'd']);
    $b = $tree->build(['d', 'c', 'b', 'a']);

    expect($a['root'])->toBe($b['root']);
});

it('produces a different root when a leaf changes', function () {
    $tree = new MerkleTree();
    $a = $tree->build(['a', 'b', 'c']);
    $b = $tree->build(['a', 'b', 'd']);

    expect($a['root'])->not->toBe($b['root']);
});

it('verifies a valid inclusion proof', function () {
    $tree = new MerkleTree();
    $built = $tree->build(['alpha', 'beta', 'gamma', 'delta', 'epsilon']);

    foreach (['alpha', 'beta', 'gamma', 'delta', 'epsilon'] as $uuid) {
        expect($tree->verify($uuid, $built['paths'][$uuid], $built['root']))
            ->toBeTrue("inclusion proof failed for {$uuid}");
    }
});

it('rejects an inclusion proof against the wrong root', function () {
    $tree = new MerkleTree();
    $built = $tree->build(['x', 'y', 'z']);
    $bogusRoot = str_repeat('a', 64);

    expect($tree->verify('x', $built['paths']['x'], $bogusRoot))->toBeFalse();
});

it('rejects an inclusion proof with a tampered sibling', function () {
    $tree = new MerkleTree();
    $built = $tree->build(['p', 'q', 'r', 's']);
    $path = $built['paths']['p'];
    $path[0]['hash'] = str_repeat('0', 64);

    expect($tree->verify('p', $path, $built['root']))->toBeFalse();
});

it('returns an empty root for an empty leaf set', function () {
    $tree = new MerkleTree();
    $built = $tree->build([]);

    expect($built['root'])->toBe(str_repeat('0', 64))
        ->and($built['paths'])->toBe([]);
});

it('handles single-leaf inputs (path is empty, root is leaf hash)', function () {
    $tree = new MerkleTree();
    $built = $tree->build(['solo']);

    expect($built['paths']['solo'])->toBe([])
        ->and($tree->verify('solo', [], $built['root']))->toBeTrue();
});

it('handles odd-tail levels via leaf doubling', function () {
    $tree = new MerkleTree();
    $built = $tree->build(['1', '2', '3']);

    expect($tree->verify('1', $built['paths']['1'], $built['root']))->toBeTrue()
        ->and($tree->verify('2', $built['paths']['2'], $built['root']))->toBeTrue()
        ->and($tree->verify('3', $built['paths']['3'], $built['root']))->toBeTrue();
});

it('domain-separates leaves to prevent second-preimage attacks', function () {
    $tree = new MerkleTree();
    $leafHash = $tree->leafHash('foo');
    $rawHash = hash('sha256', 'foo');

    expect($leafHash)->not->toBe($rawHash);
});
