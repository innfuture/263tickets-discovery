<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * Produces an SVG QR for a `qr_payload` string. Delegates to a real
 * encoder when one is installed (`chillerlan/php-qrcode` or
 * `endroid/qr-code` — both are MIT-licensed, drop-in additions). When
 * neither is present, returns a visibly-distinct placeholder SVG so
 * the integrator can see what's missing without crashing the pass
 * generation pipeline.
 *
 * Install one with:
 *     composer require chillerlan/php-qrcode
 * or:
 *     composer require endroid/qr-code
 */
class QrCodeGenerator
{
    public function svg(string $payload, int $size = 256): string
    {
        if (class_exists(QRCode::class)) {
            return $this->withChillerlan($payload, $size);
        }
        if (class_exists(Builder::class)) {
            return $this->withEndroid($payload, $size);
        }

        return $this->placeholder($payload, $size);
    }

    protected function withChillerlan(string $payload, int $size): string
    {
        $options = new QROptions([
            'version' => 5,
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'svgViewBoxSize' => $size,
        ]);

        return (new QRCode($options))->render($payload);
    }

    protected function withEndroid(string $payload, int $size): string
    {
        return Builder::create()
            ->data($payload)
            ->size($size)
            ->writer(new SvgWriter)
            ->build()
            ->getString();
    }

    /**
     * Fallback rendering — not a real QR. Clearly labelled so QA
     * spots the missing dependency without confusing it for a bug
     * in payload generation.
     */
    protected function placeholder(string $payload, int $size): string
    {
        $short = htmlspecialchars(substr($payload, 0, 36), ENT_QUOTES);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
  <rect width="{$size}" height="{$size}" fill="#fee2e2" stroke="#b91c1c" stroke-width="2"/>
  <text x="50%" y="40%" font-family="monospace" font-size="14" text-anchor="middle" fill="#7f1d1d">QR not rendered</text>
  <text x="50%" y="55%" font-family="monospace" font-size="10" text-anchor="middle" fill="#7f1d1d">install chillerlan/php-qrcode</text>
  <text x="50%" y="75%" font-family="monospace" font-size="8" text-anchor="middle" fill="#7f1d1d">{$short}</text>
</svg>
SVG;
    }
}
