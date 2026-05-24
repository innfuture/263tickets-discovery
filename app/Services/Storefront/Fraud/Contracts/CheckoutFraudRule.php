<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud\Contracts;

use App\Models\CheckoutSession;
use App\Services\Storefront\Fraud\Data\CheckoutFraudVerdict;
use Illuminate\Http\Request;

/**
 * Pre-charge fraud rule. Pipeline lives in `CheckoutFraudEngine`,
 * invoked from `CheckoutController::pay()` right before we lock the
 * session and call the gateway. Rules are order-independent — engine
 * combines verdicts and picks the strongest (deny > warn > allow),
 * mirrors how `FraudEngine` works on the scanner side.
 */
interface CheckoutFraudRule
{
    public function evaluate(CheckoutSession $session, Request $request): CheckoutFraudVerdict;
}
