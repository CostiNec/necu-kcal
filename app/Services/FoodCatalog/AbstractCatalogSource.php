<?php

namespace App\Services\FoodCatalog;

use App\Support\HtmlText;

abstract class AbstractCatalogSource implements CatalogSource
{
    protected function number(
        mixed $value,
        float $maximum = 100000
    ): ?float {
        if (is_string($value)) {
            $value = trim($value);

            if ($value === '' || in_array(
                mb_strtolower($value),
                ['n', '-', 'null'],
                true
            )) {
                return null;
            }

            if (mb_strtolower($value) === 'tr') {
                return 0.0;
            }

            $value = str_replace(',', '.', $value);
        }

        if (
            ! is_numeric($value)
            || ! is_finite((float) $value)
            || (float) $value < 0
            || (float) $value > $maximum
        ) {
            return null;
        }

        return round((float) $value, 3);
    }

    protected function text(mixed $value, int $limit = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(HtmlText::decode($value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    /**
     * @param  array<string, float|null>  $nutrients
     * @param  array<string, string>  $translations
     * @param  array<int, array<string, mixed>>  $aliases
     * @return array<string, mixed>|null
     */
    protected function record(
        string $externalId,
        string $name,
        array $nutrients,
        array $translations = [],
        array $aliases = [],
        string $basisUnit = 'g',
        bool $common = false,
        ?int $commonPriority = null,
        ?string $extraSearchText = null
    ): ?array {
        $calories = $nutrients['calories'] ?? null;

        if ($externalId === '' || $name === '' || $calories === null) {
            return null;
        }

        $protein = $nutrients['protein'] ?? null;
        $carbohydrates = $nutrients['carbohydrates'] ?? null;
        $fat = $nutrients['fat'] ?? null;
        $translationNames = array_values($translations);
        $aliasNames = array_values(array_filter(array_map(
            fn (array $alias) => $alias['name'] ?? null,
            $aliases
        )));

        return [
            'external_id' => mb_substr($externalId, 0, 64),
            'food' => [
                'user_id' => null,
                'external_id' => mb_substr($externalId, 0, 64),
                'food_type' => 'generic',
                'search_priority' => 0,
                'is_common' => $common,
                'common_priority' => $commonPriority,
                'name' => mb_substr($name, 0, 255),
                'brand' => null,
                'main_locale' => 'en',
                'barcode' => null,
                'calories' => round($calories, 2),
                'nutrition_basis_amount' => 100,
                'nutrition_basis_unit' => $basisUnit,
                'protein' => $protein,
                'carbohydrates' => $carbohydrates,
                'fat' => $fat,
                'saturated_fat' => $nutrients['saturated_fat'] ?? null,
                'fibre' => $nutrients['fibre'] ?? null,
                'sugar' => $nutrients['sugar'] ?? null,
                'sodium' => isset($nutrients['sodium_mg'])
                    ? round($nutrients['sodium_mg'] / 1000, 3)
                    : null,
                'salt' => isset($nutrients['sodium_mg'])
                    ? round(($nutrients['sodium_mg'] / 1000) * 2.5, 3)
                    : null,
                'package_quantity' => null,
                'package_unit' => null,
                'is_public' => true,
                'nutrition_complete' => $protein !== null
                    && $carbohydrates !== null
                    && $fat !== null,
                'is_active' => true,
                'data_completeness' => null,
                'popularity_score' => 0,
                'image_url' => null,
                'search_text' => collect([
                    $name,
                    ...$translationNames,
                    ...$aliasNames,
                    $extraSearchText,
                ])->filter()->unique()->implode(' '),
                'source_updated_at' => null,
            ],
            'translations' => [
                'en' => $name,
                ...$translations,
            ],
            'aliases' => $aliases,
        ];
    }
}
