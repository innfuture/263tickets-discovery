<?php

return [
    // null | log | sentry
    'driver' => env('TELEMETRY_DRIVER', 'log'),

    'log' => [
        // Laravel log channel telemetry events go to. Configure a
        // dedicated channel in config/logging.php (e.g. JSON to a
        // separate file) for clean parseable output.
        'channel' => env('TELEMETRY_LOG_CHANNEL', 'stack'),
    ],

    // sentry config lives in config/sentry.php once
    // sentry/sentry-laravel is installed.
];
