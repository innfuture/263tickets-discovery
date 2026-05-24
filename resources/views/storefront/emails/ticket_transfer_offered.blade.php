@component('mail::message')
# You've been sent a ticket

**{{ $transfer->from_email }}** has offered you a ticket@if ($eventName) to **{{ $eventName }}**@endif.

@if ($transfer->message)
> {{ $transfer->message }}
@endif

This link is valid until {{ optional($transfer->expires_at)->format('D, M j Y · H:i') }}.

@component('mail::button', ['url' => $claimUrl])
View transfer
@endcomponent

If you don't recognise this sender, you can ignore this email — the
ticket stays with them.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
