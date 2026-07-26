<?php

return [
    'source_code' => 'usda_food_data_central',

    'datasets' => [
        'foundation' => [
            'url' => env(
                'USDA_FOUNDATION_FOODS_URL',
                'https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_foundation_food_json_2026-04-30.zip'
            ),
            'path' => storage_path(
                'app/imports/usda-foundation-foods.zip'
            ),
            'root_key' => 'FoundationFoods',
        ],
        'sr-legacy' => [
            'url' => env(
                'USDA_SR_LEGACY_URL',
                'https://fdc.nal.usda.gov/fdc-datasets/FoodData_Central_sr_legacy_food_json_2018-04.zip'
            ),
            'path' => storage_path(
                'app/imports/usda-sr-legacy-foods.zip'
            ),
            'root_key' => 'SRLegacyFoods',
        ],
    ],

    'batch_size' => 500,
    'progress_every' => 1000,
];
