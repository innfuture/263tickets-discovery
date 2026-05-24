<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| SMS provider
|--------------------------------------------------------------------------
|
| Selects the SmsProvider implementation bound at runtime. `null` is
| the safe default for dev/CI — it logs the intended message instead
| of spending credits. Production deployments flip to `twilio`.
|
*/

return [

    'driver' => env('SMS_DRIVER', 'null'),

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from_number' => env('TWILIO_FROM_NUMBER'),
        'timeout_seconds' => (int) env('TWILIO_TIMEOUT', 10),
    ],

];
