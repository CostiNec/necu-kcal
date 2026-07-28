<?php

namespace App\Services\NutritionEstimation;

use RuntimeException;

abstract class AbstractNutritionEstimator implements NutritionEstimator
{
    protected function instructions(string $locale): string
    {
        return <<<PROMPT
You estimate food weight and nutrition density for a food diary from text and up to two photos.
Photos may show the same food from different angles or show a package label; never double-count food merely because it appears in multiple photos.
Estimate the total edible weight consumed in grams.
Return calories in kcal per 100 g and protein, carbohydrates, fat, and fibre in grams per 100 g for the combined food or meal.
Use a weighted-average nutrition density when the meal contains multiple foods.
Use package nutrition labels when they are legible. Otherwise use typical portions and common preparation methods when details are missing.
All numeric values must be non-negative. Calories per 100 g and total weight must be greater than zero.
Use a short food name in the same language as the user's description.
State important portion or preparation assumptions briefly.
Confidence must reflect how precisely the description identifies foods and quantities.
Do not provide health or medical advice. The application's locale is {$locale}.
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    protected function schema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'name' => ['type' => 'string'],
                'weight_grams' => ['type' => 'number'],
                'calories_per_100g' => ['type' => 'number'],
                'protein_per_100g' => ['type' => 'number'],
                'carbohydrates_per_100g' => ['type' => 'number'],
                'fat_per_100g' => ['type' => 'number'],
                'fibre_per_100g' => ['type' => 'number'],
                'confidence' => [
                    'type' => 'string',
                    'enum' => ['low', 'medium', 'high'],
                ],
                'assumptions' => ['type' => 'string'],
            ],
            'required' => [
                'name',
                'weight_grams',
                'calories_per_100g',
                'protein_per_100g',
                'carbohydrates_per_100g',
                'fat_per_100g',
                'fibre_per_100g',
                'confidence',
                'assumptions',
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     weight_grams: float,
     *     calories_per_100g: float,
     *     protein_per_100g: float,
     *     carbohydrates_per_100g: float,
     *     fat_per_100g: float,
     *     fibre_per_100g: float,
     *     confidence: string,
     *     assumptions: string
     * }
     */
    protected function validatedEstimate(string $output): array
    {
        $estimate = json_decode($output, true);

        if (! is_array($estimate)) {
            throw new RuntimeException(
                'The AI provider returned invalid JSON.'
            );
        }

        $name = trim((string) ($estimate['name'] ?? ''));
        $assumptions = trim((string) ($estimate['assumptions'] ?? ''));
        $confidence = (string) ($estimate['confidence'] ?? '');
        $nutrients = [
            'weight_grams' => [0.01, 1000000],
            'calories_per_100g' => [0.01, 100000],
            'protein_per_100g' => [0, 10000],
            'carbohydrates_per_100g' => [0, 10000],
            'fat_per_100g' => [0, 10000],
            'fibre_per_100g' => [0, 10000],
        ];

        if (
            $name === ''
            || mb_strlen($name) > 255
            || mb_strlen($assumptions) > 1000
            || ! in_array($confidence, ['low', 'medium', 'high'], true)
        ) {
            throw new RuntimeException(
                'The AI provider returned an invalid estimate.'
            );
        }

        $validated = [];

        foreach ($nutrients as $key => [$minimum, $maximum]) {
            $value = $estimate[$key] ?? null;

            if (
                ! is_numeric($value)
                || ! is_finite((float) $value)
                || (float) $value < $minimum
                || (float) $value > $maximum
            ) {
                throw new RuntimeException(
                    'The AI provider returned invalid nutrition values.'
                );
            }

            $validated[$key] = round((float) $value, 2);
        }

        return [
            'name' => $name,
            ...$validated,
            'confidence' => $confidence,
            'assumptions' => $assumptions,
        ];
    }
}
