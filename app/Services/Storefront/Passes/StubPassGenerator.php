<?php

declare(strict_types=1);

namespace App\Services\Storefront\Passes;

use App\Models\OrderItem;
use App\Services\Storefront\Contracts\PassGenerator;

/**
 * SVG-based "pass" — works without any vendor dependency. Suitable
 * for the web ticket viewer; not a real `.pkpass`. Swap to
 * ApplePassKitGenerator / GoogleWalletPassGenerator when their
 * credentials are configured.
 */
class StubPassGenerator implements PassGenerator
{
    public function __construct(protected QrCodeGenerator $qr) {}

    public function identifier(): string
    {
        return 'pdf';
    }

    public function contentType(): string
    {
        return 'image/svg+xml';
    }

    public function filename(OrderItem $item): string
    {
        return 'ticket-'.$item->order->reference.'-'.$item->id.'.svg';
    }

    public function build(OrderItem $item): string
    {
        $event = $item->order->event;
        $name = htmlspecialchars((string) ($event?->name ?? 'Event'), ENT_QUOTES);
        $venue = htmlspecialchars((string) ($event?->venue_name ?? ''), ENT_QUOTES);
        $when = $event?->starts_at?->format('D, M j Y · H:i') ?? '';
        $attendee = htmlspecialchars((string) ($item->attendee_name ?? ''), ENT_QUOTES);
        $tier = htmlspecialchars((string) ($item->ticket_type ?? ''), ENT_QUOTES);
        $reference = htmlspecialchars((string) $item->order->reference, ENT_QUOTES);
        $payload = (string) ($item->qr_payload ?? '');

        $qr = $this->qr->svg($payload, 200);
        // Strip any leading XML declaration the encoder added so we
        // can splice the SVG directly inside our parent <svg>.
        $qr = preg_replace('/^<\?xml[^>]*\?>\s*/', '', $qr);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="380" height="560" viewBox="0 0 380 560">
  <rect width="380" height="560" rx="14" fill="#0b1220"/>
  <text x="24" y="48" fill="#fff" font-family="-apple-system, sans-serif" font-size="22" font-weight="700">{$name}</text>
  <text x="24" y="74" fill="#9ca3af" font-family="-apple-system, sans-serif" font-size="13">{$venue} · {$when}</text>

  <rect x="24" y="100" width="332" height="80" rx="8" fill="#1f2937"/>
  <text x="40" y="128" fill="#9ca3af" font-family="-apple-system, sans-serif" font-size="10" letter-spacing="1">ATTENDEE</text>
  <text x="40" y="150" fill="#fff" font-family="-apple-system, sans-serif" font-size="16">{$attendee}</text>
  <text x="40" y="170" fill="#9ca3af" font-family="-apple-system, sans-serif" font-size="12">{$tier}</text>

  <g transform="translate(90,200)">{$qr}</g>

  <text x="190" y="430" fill="#9ca3af" font-family="monospace" font-size="11" text-anchor="middle">{$reference}</text>
  <text x="190" y="450" fill="#6b7280" font-family="monospace" font-size="9" text-anchor="middle">{$payload}</text>
</svg>
SVG;
    }
}
