@component('mail::message')
# Sign in to your account

Click the link below to sign in. This link is valid for **{{ $ttlMinutes }} minutes** and can only be used once.

@component('mail::button', ['url' => $url])
Sign me in
@endcomponent

If you didn't request this, ignore this email — no one can use it
without access to your inbox.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
