@component('mail::message')
# Sign in to the Stakeholder Portal

Use the link below to access your stakeholder dashboard — manage
invitations, applications, deliverables, payments, and event
engagements.

The link is valid for **{{ $ttlMinutes }} minutes** and can be used
once.

@component('mail::button', ['url' => $url])
Sign me in
@endcomponent

If this email was unexpected, you can ignore it.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
