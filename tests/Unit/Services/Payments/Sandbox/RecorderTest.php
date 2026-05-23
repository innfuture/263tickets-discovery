<?php

declare(strict_types=1);

use App\Services\Payments\Sandbox\Recorder;

it('redacts sensitive keys from the request snapshot before persistence', function () {
    $recorder = new Recorder;

    // Use the diff method's flattener to test sanitisation indirectly.
    // Since record() requires DB, we exercise the sanitiser through
    // reflection on the protected method.
    $ref = new ReflectionMethod($recorder, 'sanitise');
    $ref->setAccessible(true);

    $clean = $ref->invoke($recorder, [
        'amount' => 1999,
        'card' => ['number' => '4242424242424242', 'cvc' => '123'],
        'Authorization' => 'Bearer sk_test_abc',
        'pan' => '4242 4242 4242 4242',
        'metadata' => ['order_id' => 'ord_5'],
    ]);

    expect($clean['amount'])->toBe(1999)
        ->and($clean['Authorization'])->toBe('__redacted__')
        ->and($clean['pan'])->toBe('__redacted__')
        ->and($clean['metadata']['order_id'])->toBe('ord_5');
});

it('flattens nested arrays for diffing', function () {
    $recorder = new Recorder;

    $ref = new ReflectionMethod($recorder, 'flatten');
    $ref->setAccessible(true);

    $flat = $ref->invoke($recorder, [
        'id' => 'pi_1',
        'amount' => 1999,
        'metadata' => ['order_id' => 'ord_5', 'tier' => 'gold'],
    ]);

    expect($flat)->toBe([
        'id' => 'pi_1',
        'amount' => 1999,
        'metadata.order_id' => 'ord_5',
        'metadata.tier' => 'gold',
    ]);
});
