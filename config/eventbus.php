<?php

return [
    // Pluggable cross-process domain event bus. Default `laravel` —
    // fires Laravel events so in-process listeners pick them up.
    // Switch to `redis_streams` once you have out-of-process consumers.
    //
    //   laravel       (default) Laravel Event::dispatch wildcards
    //   redis_streams XADD to per-event Redis streams
    //   nats          NATS subject `domain.<type>`
    //   null          quiet — used in tests
    'driver' => env('EVENTBUS_DRIVER', 'laravel'),

    'redis_streams' => [
        'connection' => env('EVENTBUS_REDIS_CONNECTION', 'default'),
        // Approximate cap per stream — older entries trimmed via
        // XADD ... MAXLEN ~ <max>. Tune for retention vs Redis RAM.
        'max_len' => (int) env('EVENTBUS_REDIS_MAX_LEN', 100_000),
    ],

    'nats' => [
        'host' => env('NATS_HOST', '127.0.0.1'),
        'port' => (int) env('NATS_PORT', 4222),
        'token' => env('NATS_TOKEN'),
        'stream_prefix' => env('NATS_STREAM_PREFIX', 'domain'),
    ],
];
