<?php

declare(strict_types=1);

use App\Models\SandboxPaymentMethod;
use App\Services\Payments\Data\ChargeRequest;
use App\Services\Payments\Data\Customer;
use App\Services\Payments\Data\Money;
use App\Services\Payments\Sandbox\MagicValues;
use App\Services\Payments\Sandbox\SavedMethods;
use Tests\TestCase;

uses(TestCase::class);

function buildSavedMethodWithoutDb(?string $nti = null): SandboxPaymentMethod
{
    // Build an unsaved model with the bits SavedMethods reads. The
    // hydrate() path does require DB, but offSessionRejectionFor() only
    // touches the model's in-memory attributes — safe without DB.
    $m = new SandboxPaymentMethod([
        'token' => 'pm_sbx_'.bin2hex(random_bytes(8)),
        'kind' => 'card',
        'brand' => 'visa',
        'network_transaction_id' => $nti,
    ]);

    return $m;
}

function buildChargeReq(array $metadata = []): ChargeRequest
{
    return new ChargeRequest(
        amount: Money::ofMajor('10.00', 'USD'),
        customer: new Customer,
        reference: 'ref-'.bin2hex(random_bytes(4)),
        description: 'Test',
        metadata: $metadata,
    );
}

it('rejects an off-session charge against a method that has no network_transaction_id', function () {
    $svc = new SavedMethods(new MagicValues);

    $code = $svc->offSessionRejectionFor(
        buildChargeReq(['off_session' => true]),
        buildSavedMethodWithoutDb(nti: null),
    );

    expect($code)->toBe('authentication_required');
});

it('allows an off-session charge once network_transaction_id is set', function () {
    $svc = new SavedMethods(new MagicValues);

    $code = $svc->offSessionRejectionFor(
        buildChargeReq(['off_session' => true]),
        buildSavedMethodWithoutDb(nti: 'mit_abc123'),
    );

    expect($code)->toBeNull();
});

it('returns null when not an off-session charge regardless of NTI presence', function () {
    $svc = new SavedMethods(new MagicValues);

    expect($svc->offSessionRejectionFor(buildChargeReq(), buildSavedMethodWithoutDb()))->toBeNull();
});

it('returns null when no saved method was hydrated', function () {
    $svc = new SavedMethods(new MagicValues);

    expect($svc->offSessionRejectionFor(buildChargeReq(['off_session' => true]), null))->toBeNull();
});

it('flags a revoked method even with off_session and NTI', function () {
    $svc = new SavedMethods(new MagicValues);

    $revoked = buildSavedMethodWithoutDb(nti: 'mit_abc');
    $revoked->revoked_at = now();

    expect($svc->offSessionRejectionFor(buildChargeReq(['off_session' => true]), $revoked))
        ->toBe('payment_method_revoked');
});
