<?php

declare(strict_types=1);

namespace App\Services\Storefront\Contracts;

use Illuminate\Http\Request;

/**
 * Bot-protection abstraction. The storefront calls `verify()` from
 * `EnsureCaptchaPassed` middleware before letting a buyer create a
 * session or join the waitlist.
 *
 * Implementations live in `app/Services/Storefront/Captcha/*`. The
 * default binding is the null provider (always-passes) so dev /
 * sandbox runs don't need a Turnstile / hCaptcha key.
 */
interface CaptchaProvider
{
    public function verify(Request $request): bool;

    /**
     * Identifier shown in error responses so the front-end knows
     * which widget to render ('turnstile', 'hcaptcha', 'recaptcha').
     */
    public function identifier(): string;

    /**
     * The public site key the front-end widget needs. Exposed at
     * `GET /api/v1/public/captcha/config` so the public app can
     * render the right widget without re-deploying.
     */
    public function siteKey(): ?string;
}
