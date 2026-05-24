<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\Storefront\Contracts\CaptchaProvider;
use Illuminate\Http\JsonResponse;

/**
 * Tells the public front-end which captcha widget to render.
 *
 *   GET /api/v1/public/captcha/config
 *
 * Returns the provider id + public site key. Front-end caches the
 * response and embeds the matching JS widget on protected forms.
 */
class CaptchaConfigController extends Controller
{
    public function __construct(protected CaptchaProvider $captcha) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => [
                'provider' => $this->captcha->identifier(),
                'site_key' => $this->captcha->siteKey(),
            ],
        ]);
    }
}
