<?php

return [
    'driver' => env('FOOD_SEARCH_DRIVER', 'database'),

    'fallback_to_database' => env(
        'FOOD_SEARCH_FALLBACK_TO_DATABASE',
        true
    ),

    'per_page' => 20,

    'typesense' => [
        'collection' => env('TYPESENSE_FOODS_COLLECTION', 'foods'),
        'max_results' => (int) env('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'max_candidates' => (int) env(
            'TYPESENSE_FOOD_MAX_CANDIDATES',
            50
        ),
        'num_typos' => env('TYPESENSE_FOOD_NUM_TYPOS', '2,2,1,1'),
    ],
];
