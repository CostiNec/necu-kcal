<?php

return [
    'batch_size' => 500,
    'progress_every' => 1000,

    'sources' => [
        'cnf' => [
            'source_code' => 'canadian_nutrient_file',
            'url' => env(
                'CNF_DOWNLOAD_URL',
                'https://open.canada.ca/data/dataset/1b6139bd-ed7e-4043-bc28-ff00e10f3109/resource/019f2a90-e3a9-489d-b6e1-f74f4ba1d006/download/cnf_fcen_all-files-data_2026.zip'
            ),
            'path' => storage_path('app/imports/cnf-2026.zip'),
        ],
        'fineli' => [
            'source_code' => 'fineli',
            'url' => env(
                'FINELI_DOWNLOAD_URL',
                'https://fineli.fi/fineli/content/file/48'
            ),
            'path' => storage_path(
                'app/imports/fineli-raw-ingredients.zip'
            ),
        ],
        'cofid' => [
            'source_code' => 'cofid',
            'url' => env(
                'COFID_DOWNLOAD_URL',
                'https://assets.publishing.service.gov.uk/media/60538b91e90e07527df82ae4/McCance_Widdowsons_Composition_of_Foods_Integrated_Dataset_2021..xlsx'
            ),
            'path' => storage_path('app/imports/cofid-2021.xlsx'),
        ],
        'afcd' => [
            'source_code' => 'afcd',
            'files' => [
                'foods' => [
                    'url' => env(
                        'AFCD_FOODS_DOWNLOAD_URL',
                        'https://www.foodstandards.gov.au/sites/default/files/2025-12/AFCD%20Release%203%20-%20Food%20Details.xlsx'
                    ),
                    'path' => storage_path(
                        'app/imports/afcd-release-3-food-details.xlsx'
                    ),
                ],
                'nutrients' => [
                    'url' => env(
                        'AFCD_NUTRIENTS_DOWNLOAD_URL',
                        'https://www.foodstandards.gov.au/sites/default/files/2025-12/AFCD%20Release%203%20-%20Nutrient%20profiles.xlsx'
                    ),
                    'path' => storage_path(
                        'app/imports/afcd-release-3-nutrients.xlsx'
                    ),
                ],
            ],
        ],
    ],
];
