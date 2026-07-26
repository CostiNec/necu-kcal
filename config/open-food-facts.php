<?php

return [
    'source_code' => 'open_food_facts',

    'download_url' => env(
        'OPEN_FOOD_FACTS_URL',
        'https://static.openfoodfacts.org/data/openfoodfacts-products.jsonl.gz'
    ),

    'import_path' => env(
        'OPEN_FOOD_FACTS_IMPORT_PATH',
        storage_path('app/imports/openfoodfacts-products.jsonl.gz')
    ),

    'batch_size' => 500,
    'max_errors' => 1000,
    'progress_every' => 10000,

    'market_tags' => [
        'RO' => [
            'en:romania',
            'ro:romania',
        ],
    ],
];
