<?php

declare(strict_types=1);

use App\Services\Payments\Data\Money;

it('converts major units to minor without float drift', function () {
    expect(Money::ofMajor('19.99', 'USD')->amountMinor)->toBe(1999);
    expect(Money::ofMajor('0.01', 'USD')->amountMinor)->toBe(1);
    expect(Money::ofMajor('1000', 'USD')->amountMinor)->toBe(100000);
});

it('round-trips through major format with two decimals', function () {
    expect(Money::ofMajor('19.99', 'USD')->major())->toBe('19.99');
    expect((new Money(1, 'USD'))->major())->toBe('0.01');
    expect((new Money(100000, 'USD'))->major())->toBe('1000.00');
});

it('normalises the EcoCash ZiG currency code', function () {
    expect(Money::ofMajor('5.00', 'zig')->currency)->toBe('ZiG');
    expect(Money::ofMajor('5.00', 'ZIG')->currency)->toBe('ZiG');
});

it('rejects negative amounts', function () {
    expect(fn () => new Money(-1, 'USD'))->toThrow(InvalidArgumentException::class);
});

it('rejects non-numeric major input', function () {
    expect(fn () => Money::ofMajor('not-a-number', 'USD'))
        ->toThrow(InvalidArgumentException::class);
});
