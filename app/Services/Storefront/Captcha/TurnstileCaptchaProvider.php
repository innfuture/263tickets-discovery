<?php

declare(strict_types=1);

namespace App\Services\Storefront\Captcha;

use App\Services\Storefront\Contracts\CaptchaProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cloudflare Turnstile. Free, doesn't track users, drop-in for the
 * front-end widget. Verifies the `cf-turnstile-response` token the
 * widget POSTs against Cloudflare's siteverify endpoint.
 */
class TurnstileCaptchaProvider implements CaptchaProvider
{
    public function __construct(
        protected string $secretKey,
        protected string $siteKey,
    ) {}

    public function verify(Request $request): bool
    {
        $token = $request->header('X-Captcha-Token') ?? $request->input('captcha_token');
        if (! is_string($token) || $token === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $this->secretKey,
                    'response' => $token,
                    'remoteip' => $request->ip(),
                ]);
        } catch (\Throwable $e) {
            Log::warning('storefront.captcha.transport_error', ['message' => $e->getMessage()]);

            return false;
        }

        $body = $response->json();

        return is_array($body) && ($body['success'] ?? false) === true;
    }

    public function identifier(): string
    {
        return 'turnstile';
    }

    public function siteKey(): ?string
    {
        return $this->siteKey;
    }
}
