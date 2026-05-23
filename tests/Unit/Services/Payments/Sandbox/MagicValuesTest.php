<?php

declare(strict_types=1);

use App\Enums\SandboxScenario;
use App\Services\Payments\Sandbox\MagicValues;

it('resolves the classic Stripe success card to the SUCCESS scenario', function () {
    $magic = new MagicValues;
    $hit = $magic->lookupCard('4242 4242 4242 4242');

    expect($hit)->not->toBeNull()
        ->and($hit['scenario'])->toBe(SandboxScenario::SUCCESS)
        ->and($hit['brand'])->toBe('visa');
});

it('strips spaces and dashes when looking up cards', function () {
    $magic = new MagicValues;
    expect($magic->lookupCard('4000-0000-0000-9995')['scenario'])->toBe(SandboxScenario::DECLINE_INSUFFICIENT);
});

it('returns null for unknown PANs (no Luhn validation)', function () {
    $magic = new MagicValues;
    expect($magic->lookupCard('1234567890123456'))->toBeNull()
        ->and($magic->lookupCard(null))->toBeNull()
        ->and($magic->lookupCard(''))->toBeNull();
});

it('matches MSISDN suffixes case-insensitively to scenarios', function () {
    $magic = new MagicValues;
    expect($magic->lookupMsisdn('263772222516'))->toBe(SandboxScenario::SUCCESS)
        ->and($magic->lookupMsisdn('263772229999'))->toBe(SandboxScenario::MOBILE_USER_CANCEL)
        ->and($magic->lookupMsisdn('263772228888'))->toBe(SandboxScenario::MOBILE_NO_RESPONSE)
        ->and($magic->lookupMsisdn('263000000000'))->toBeNull();
});

it('resolves ACH account numbers to return codes', function () {
    $magic = new MagicValues;
    expect($magic->lookupAch('000111111111'))->toBe(SandboxScenario::ACH_R01_NSF)
        ->and($magic->lookupAch('000222222222'))->toBe(SandboxScenario::ACH_R02_CLOSED)
        ->and($magic->lookupAch('999999999999'))->toBeNull();
});

it('normalises IBANs before lookup', function () {
    $magic = new MagicValues;
    expect($magic->lookupIban('de89 3704 0044 0532 0130 00'))->toBe(SandboxScenario::SUCCESS)
        ->and($magic->lookupIban('DE89370400440532013111'))->toBe(SandboxScenario::SEPA_MANDATE_REVOKED);
});
