<?php

return [
    'provider' => env('FOOD_TRANSLATION_PROVIDER', 'deepl'),

    'deepl' => [
        'key' => env('DEEPL_API_KEY'),
        'url' => env(
            'DEEPL_API_URL',
            'https://api-free.deepl.com/v2/translate'
        ),
        'timeout' => (int) env('DEEPL_TIMEOUT', 60),
        // DeepL allows 128 KiB total; leave room for headers.
        'max_request_bytes' => (int) env(
            'DEEPL_MAX_REQUEST_BYTES',
            112 * 1024
        ),
    ],
];
