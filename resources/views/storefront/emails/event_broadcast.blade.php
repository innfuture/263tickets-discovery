@component('mail::message')
# {{ $subjectLine }}

{!! nl2br(e($body)) !!}

Thanks,<br>
{{ config('app.name') }}
@endcomponent
