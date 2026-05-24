<?php

declare(strict_types=1);

namespace App\Services\Storefront\Fraud\Rules;

use App\Models\CheckoutSession;
use App\Services\Storefront\Fraud\Contracts\CheckoutFraudRule;
use App\Services\Storefront\Fraud\Data\CheckoutFraudVerdict;
use Illuminate\Http\Request;

/**
 * Hard-deny known fraudster emails or disposable-mailbox domains.
 * The blocklist lives in `config/storefront.php.fraud.blocked_emails`
 * and `…blocked_email_domains` so it can be updated without a deploy
 * (env var or a tiny dashboard later).
 */
class EmailBlocklistRule implements CheckoutFraudRule
{
    public function evaluate(CheckoutSession $session, Request $request): CheckoutFraudVerdict
    {
        $email = strtolower(trim((string) $session->buyer_email));
        if ($email === '') {
            return CheckoutFraudVerdict::allow();
        }

        $blockedEmails = array_map('strtolower', (array) config('storefront.fraud.blocked_emails', []));
        if (in_array($email, $blockedEmails, true)) {
            return CheckoutFraudVerdict::deny('blocked_email', 5, ['email' => $email]);
        }

        $domain = (string) (explode('@', $email, 2)[1] ?? '');
        $blockedDomains = array_map('strtolower', (array) config('storefront.fraud.blocked_email_domains', []));
        if ($domain !== '' && in_array($domain, $blockedDomains, true)) {
            return CheckoutFraudVerdict::deny('blocked_email_domain', 4, ['domain' => $domain]);
        }

        return CheckoutFraudVerdict::allow();
    }
}
