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

    protected function dayInstructions(string $locale): string
    {
        return <<<PROMPT
You reconstruct one complete food-diary day from the user's text and up to ten photos.
Photos are provided in the order selected. They may show different meals, the same meal from multiple angles, ingredients, packaging, or nutrition labels.
Never double-count food merely because it appears in multiple photos. Use the user's text to connect photos to breakfast, lunch, dinner, or snacks.
Return one entry for each distinct food or composed dish that was consumed. Do not split a composed dish into invisible ingredients, but separate visually distinct foods when their portions can reasonably be estimated.
For every entry, estimate the total edible weight consumed in grams. Return calories in kcal per 100 g and protein, carbohydrates, fat, and fibre in grams per 100 g.
Use package nutrition labels when they are legible. Otherwise use typical portions and common preparation methods when details are missing.
Assign every entry to exactly one of: breakfast, lunch, dinner, snacks. When timing is unclear, use the description and photo order; mention uncertainty in assumptions.
All numeric values must be non-negative. Calories per 100 g and total weight must be greater than zero.
Use short food names in the same language as the user's description. State important portion, timing, or preparation assumptions briefly for each entry.
Confidence must reflect how precisely the food and quantity were identified.
Return no more than 30 entries. Do not provide health or medical advice. The application's locale is {$locale}.
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
            'properties' => $this->estimateProperties(),
            'required' => $this->estimateRequiredProperties(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function daySchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'entries' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'meal' => [
                                'type' => 'string',
                                'enum' => [
                                    'breakfast',
                                    'lunch',
                                    'dinner',
                                    'snacks',
                                ],
                            ],
                            ...$this->estimateProperties(),
                        ],
                        'required' => [
                            'meal',
                            ...$this->estimateRequiredProperties(),
                        ],
                    ],
                ],
            ],
            'required' => ['entries'],
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

        return $this->validatedEstimateData($estimate);
    }

    /**
     * @return array{
     *     entries: array<int, array{
     *         meal: string,
     *         name: string,
     *         weight_grams: float,
     *         calories_per_100g: float,
     *         protein_per_100g: float,
     *         carbohydrates_per_100g: float,
     *         fat_per_100g: float,
     *         fibre_per_100g: float,
     *         confidence: string,
     *         assumptions: string
     *     }>
     * }
     */
    protected function validatedDayEstimate(string $output): array
    {
        $estimate = json_decode($output, true);
        $entries = is_array($estimate) ? ($estimate['entries'] ?? null) : null;

        if (
            ! is_array($entries)
            || $entries === []
            || count($entries) > 30
        ) {
            throw new RuntimeException(
                'The AI provider returned an invalid day estimate.'
            );
        }

        $validated = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                throw new RuntimeException(
                    'The AI provider returned an invalid day entry.'
                );
            }

            $meal = (string) ($entry['meal'] ?? '');

            if (! in_array(
                $meal,
                ['breakfast', 'lunch', 'dinner', 'snacks'],
                true
            )) {
                throw new RuntimeException(
                    'The AI provider returned an invalid meal.'
                );
            }

            $validated[] = [
                'meal' => $meal,
                ...$this->validatedEstimateData($entry),
            ];
        }

        return ['entries' => $validated];
    }

    /**
     * @param  array<string, mixed>  $estimate
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
    private function validatedEstimateData(array $estimate): array
    {
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

    /**
     * @return array<string, mixed>
     */
    private function estimateProperties(): array
    {
        return [
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
        ];
    }

    /**
     * @return array<int, string>
     */
    private function estimateRequiredProperties(): array
    {
        return [
            'name',
            'weight_grams',
            'calories_per_100g',
            'protein_per_100g',
            'carbohydrates_per_100g',
            'fat_per_100g',
            'fibre_per_100g',
            'confidence',
            'assumptions',
        ];
    }
}
