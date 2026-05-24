<?php

return [
    // Pluggable cross-process domain event bus. Default `laravel` —
    // fires Laravel events so in-process listeners pick them up.
    // Switch to `redis_streams` once you have out-of-process consumers.
    //
    //   laravel       (default) Laravel Event::dispatch wildcards
    //   redis_streams XADD to per-event Redis streams
    //   null          quiet — used in tests
    'driver' => env('EVENTBUS_DRIVER', 'laravel'),

    'redis_streams' => [
        'connection' => env('EVENTBUS_REDIS_CONNECTION', 'default'),
        // Approximate cap per stream — older entries trimmed via
        // XADD ... MAXLEN ~ <max>. Tune for retention vs Redis RAM.
        'max_len' => (int) env('EVENTBUS_REDIS_MAX_LEN', 100_000),
    ],
];
