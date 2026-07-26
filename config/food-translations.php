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
    ],
];
