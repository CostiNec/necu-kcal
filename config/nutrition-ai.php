<?php

return [
    'provider' => env('AI_NUTRITION_PROVIDER', 'gemini'),
    'timeout' => (int) env('AI_NUTRITION_TIMEOUT', 120),
    'full_day_timeout' => (int) env(
        'AI_NUTRITION_FULL_DAY_TIMEOUT',
        120
    ),
];
