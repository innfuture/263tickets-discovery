<?php

declare(strict_types=1);

use App\Services\Sms\NullSmsProvider;
use Tests\TestCase;

uses(TestCase::class);

it('returns a synthetic message id rather than null', function () {
    $provider = new NullSmsProvider();
    $id = $provider->send('+15551234567', 'hello');

    expect($id)->toBeString()->toStartWith('null-sms-');
});

it('identifies as null', function () {
    expect((new NullSmsProvider())->identifier())->toBe('null');
});
