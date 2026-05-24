<?php

/*
|--------------------------------------------------------------------------
| AI services configuration
|--------------------------------------------------------------------------
|
| Two pluggable surfaces — embeddings (for vector search) and the
| organizer assistant (for copy / refund-reply / sales-insight).
|
| Defaults are the no-network stubs so dev runs work out of the
| box. Production flips drivers + sets API keys.
|
*/

return [
    'embeddings' => [
        // null | openai
        'driver' => env('AI_EMBEDDINGS_DRIVER', 'stub'),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_EMBEDDINGS_MODEL', 'text-embedding-3-small'),
            'dimensions' => (int) env('AI_EMBEDDINGS_DIMS', 1536),
        ],
    ],

    'assistant' => [
        // stub | claude | openai
        'driver' => env('AI_ASSISTANT_DRIVER', 'stub'),
        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('AI_ASSISTANT_MODEL', 'claude-sonnet-4-6'),
        ],
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('AI_ASSISTANT_MODEL_OPENAI', 'gpt-4o-mini'),
        ],
    ],
];
