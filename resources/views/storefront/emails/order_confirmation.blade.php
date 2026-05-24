@component('mail::message')
# You're in.

Thank you, {{ $order->buyer_name ?: 'friend' }} — your order **{{ $order->reference }}** for **{{ $order->event?->name }}** is confirmed.

@if ($order->event)
**When:** {{ optional($order->event->starts_at)->format('D, M j Y · H:i') }}
**Where:** {{ $order->event->venue_name }}{{ $order->event->city ? ', '.$order->event->city : '' }}
@endif

@component('mail::table')
| Ticket | Attendee |
|:-------|:---------|
@foreach ($order->items as $item)
| {{ $item->ticket_type }} | {{ $item->attendee_name }} |
@endforeach
@endcomponent

**Total:** {{ number_format($order->total_cents / 100, 2) }} {{ $order->currency }}

@component('mail::button', ['url' => $receiptUrl])
View tickets
@endcomponent

This link is valid for a limited time — re-request from the
storefront if it expires.

Thanks,<br>
{{ optional($order->organization)->brand_name ?? config('app.name') }}
@endcomponent
