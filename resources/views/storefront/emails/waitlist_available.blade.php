@component('mail::message')
# Tickets are available

A ticket you waitlisted@if ($eventName) for **{{ $eventName }}**@endif has freed up.

You requested **{{ $entry->quantity_requested }}** {{ $entry->quantity_requested === 1 ? 'ticket' : 'tickets' }} — head to checkout below before this window closes and the slot rolls to the next person on the list.

@component('mail::button', ['url' => $convertUrl])
Buy now
@endcomponent

If you no longer need these tickets, simply ignore this email and we'll
release the inventory automatically.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
