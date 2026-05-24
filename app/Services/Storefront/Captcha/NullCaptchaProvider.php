<?php

declare(strict_types=1);

namespace App\Services\Storefront\Captcha;

use App\Services\Storefront\Contracts\CaptchaProvider;
use Illuminate\Http\Request;

/**
 * Always-passes captcha. The default binding so dev / sandbox runs
 * aren't blocked. Swap to TurnstileCaptchaProvider in production by
 * setting STOREFRONT_CAPTCHA_PROVIDER=turnstile in env.
 */
class NullCaptchaProvider implements CaptchaProvider
{
    public function verify(Request $request): bool
    {
        return true;
    }

    public function identifier(): string
    {
        return 'null';
    }

    public function siteKey(): ?string
    {
        return null;
    }
}
