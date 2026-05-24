<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Public;

use App\Enums\EventStatus;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Storefront\TicketReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Embeddable buy-button widget. An organizer drops
 *
 *   <div data-example-app-event="zim-jazz-fest"></div>
 *   <script src="https://app.example.com/widget/v1/embed.js" async></script>
 *
 * onto their marketing site; the script renders a "Buy tickets"
 * button + price/availability sourced from the JSON endpoint below.
 * Clicking the button opens our hosted checkout in a new tab.
 *
 *   GET /widget/v1/embed.js                          — JS asset (browser cached)
 *   GET /widget/v1/event/{slug}.json                 — event summary for the widget
 */
class WidgetController extends Controller
{
    public function __construct(protected TicketReservation $reservation) {}

    public function script(): Response
    {
        $script = <<<'JS'
(function () {
  'use strict';
  var origin = (function () {
    try {
      var s = document.currentScript;
      if (s && s.src) return new URL(s.src).origin;
    } catch (_) {}
    return '';
  })();

  function render(node, data) {
    var btn = document.createElement('a');
    btn.href = origin + '/events/' + encodeURIComponent(data.slug);
    btn.target = '_blank';
    btn.rel = 'noopener';
    btn.style.cssText = [
      'display:inline-flex','align-items:center','gap:8px',
      'padding:10px 18px','border-radius:8px',
      'background:#0b1220','color:#fff','font-family:system-ui,sans-serif',
      'font-size:14px','font-weight:600','text-decoration:none',
      'box-shadow:0 1px 3px rgba(0,0,0,0.1)'
    ].join(';');
    btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9h18M3 9v6a2 2 0 002 2h14a2 2 0 002-2V9M3 9a2 2 0 012-2h14a2 2 0 012 2"/></svg>'
      + '<span>' + (data.is_sold_out ? 'Sold out' : 'Buy tickets — ' + data.price_label) + '</span>';
    if (data.is_sold_out) {
      btn.style.background = '#9ca3af';
      btn.style.pointerEvents = 'none';
    }
    node.innerHTML = '';
    node.appendChild(btn);
  }

  function mount() {
    var nodes = document.querySelectorAll('[data-example-app-event]:not([data-example-app-mounted])');
    nodes.forEach(function (node) {
      var slug = node.getAttribute('data-example-app-event');
      if (!slug) return;
      node.setAttribute('data-example-app-mounted', '1');
      fetch(origin + '/widget/v1/event/' + encodeURIComponent(slug) + '.json')
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (j) { if (j && j.data) render(node, j.data); })
        .catch(function () {});
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mount);
  } else {
    mount();
  }

  // Observe future additions — common with SPAs / CMS embeds.
  if (window.MutationObserver) {
    new MutationObserver(mount).observe(document.body, { childList: true, subtree: true });
  }
})();
JS;

        return response($script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function event(string $slug): JsonResponse
    {
        $event = Event::query()
            ->where('slug', $slug)
            ->whereIn('status', array_map(fn (EventStatus $s) => $s->value, array_filter(EventStatus::cases(), fn ($s) => $s->isPubliclyVisible())))
            ->where('visibility', EventVisibility::Public->value)
            ->with(['ticketCategories' => fn ($q) => $q->where('is_visible', true)->orderBy('sort_order')])
            ->first();

        if (! $event) {
            return response()->json(['error' => 'event_not_found'], 404)
                ->header('Access-Control-Allow-Origin', '*');
        }

        $cheapest = $event->ticketCategories->min('base_price');
        $currency = optional($event->ticketCategories->first())->base_currency ?? 'USD';
        $priceLabel = $cheapest !== null
            ? number_format((float) $cheapest, 2).' '.$currency
            : 'Free';

        return response()->json([
            'data' => [
                'slug' => $event->slug,
                'name' => $event->name,
                'starts_at' => optional($event->starts_at)->toIso8601String(),
                'is_sold_out' => $event->isSoldOut(),
                'price_label' => $event->isSoldOut() ? 'Sold out' : $priceLabel,
                'currency' => $currency,
            ],
        ])->header('Access-Control-Allow-Origin', '*')
            ->header('Cache-Control', 'public, max-age=30');
    }
}
