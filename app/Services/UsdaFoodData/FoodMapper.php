<?php

namespace App\Services\UsdaFoodData;

use App\Support\HtmlText;
use DateTimeImmutable;
use Throwable;

class FoodMapper
{
    public const SKIP_MISSING_SOURCE_ID = 'missing_source_id';

    public const SKIP_MISSING_NAME = 'missing_name';

    public const SKIP_MISSING_ENERGY = 'missing_energy';

    /**
     * @param  array<string, mixed>  $product
     * @return array{food: array<string, mixed>, external_id: string}|null
     */
    public function map(array $product): ?array
    {
        return $this->mapWithReason($product)['product'];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{
     *     product: array{
     *         food: array<string, mixed>,
     *         external_id: string
     *     }|null,
     *     skipped_reason: string|null
     * }
     */
    public function mapWithReason(array $product): array
    {
        $externalId = $this->stringId($product['fdcId'] ?? null);
        $name = $this->string($product['description'] ?? null);

        if ($externalId === null) {
            return $this->skipped(self::SKIP_MISSING_SOURCE_ID);
        }

        if ($name === null) {
            return $this->skipped(self::SKIP_MISSING_NAME);
        }

        $nutrients = $this->nutrients($product['foodNutrients'] ?? null);
        $calories = $this->first($nutrients, [1008, 2047, 2048]);

        if ($calories === null && isset($nutrients[1062])) {
            $calories = round($nutrients[1062] / 4.184, 2);
        }

        if ($calories === null || $calories < 0 || $calories > 10000) {
            return $this->skipped(self::SKIP_MISSING_ENERGY);
        }

        $protein = $this->bounded($nutrients[1003] ?? null);
        $fat = $this->bounded($nutrients[1004] ?? null);
        $carbohydrates = $this->bounded($nutrients[1005] ?? null);
        $fibre = $this->bounded($nutrients[1079] ?? null);
        $sodiumMg = $this->bounded($nutrients[1093] ?? null, 100000);
        $category = $this->string(
            is_array($product['foodCategory'] ?? null)
                ? ($product['foodCategory']['description'] ?? null)
                : null
        );

        return [
            'product' => [
                'external_id' => $externalId,
                'food' => [
                    'user_id' => null,
                    'external_id' => $externalId,
                    'food_type' => 'generic',
                    'search_priority' => 0,
                    'name' => $name,
                    'brand' => null,
                    'main_locale' => 'en',
                    'barcode' => null,
                    'calories' => round($calories, 2),
                    'nutrition_basis_amount' => 100,
                    'nutrition_basis_unit' => 'g',
                    'protein' => $protein,
                    'carbohydrates' => $carbohydrates,
                    'fat' => $fat,
                    'saturated_fat' => $this->bounded(
                        $nutrients[1258] ?? null
                    ),
                    'fibre' => $fibre,
                    'sugar' => $this->bounded(
                        $this->first($nutrients, [2000, 1063])
                    ),
                    'sodium' => $sodiumMg === null
                        ? null
                        : round($sodiumMg / 1000, 3),
                    'salt' => $sodiumMg === null
                        ? null
                        : round(($sodiumMg / 1000) * 2.5, 3),
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
                        $category,
                        $this->string($product['scientificName'] ?? null),
                    ])->filter()->unique()->implode(' '),
                    'source_updated_at' => $this->date(
                        $product['publicationDate'] ?? null
                    ),
                ],
            ],
            'skipped_reason' => null,
        ];
    }

    /**
     * @return array{product: null, skipped_reason: string}
     */
    private function skipped(string $reason): array
    {
        return [
            'product' => null,
            'skipped_reason' => $reason,
        ];
    }

    /**
     * @return array<int, float>
     */
    private function nutrients(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $nutrients = [];

        foreach ($items as $item) {
            if (! is_array($item) || ! is_array($item['nutrient'] ?? null)) {
                continue;
            }

            $id = $item['nutrient']['id'] ?? null;
            $amount = $item['amount'] ?? null;

            if (
                is_numeric($id)
                && is_numeric($amount)
                && is_finite((float) $amount)
            ) {
                $nutrients[(int) $id] = (float) $amount;
            }
        }

        return $nutrients;
    }

    /**
     * @param  array<int, float>  $nutrients
     * @param  array<int, int>  $ids
     */
    private function first(array $nutrients, array $ids): ?float
    {
        foreach ($ids as $id) {
            if (isset($nutrients[$id])) {
                return $nutrients[$id];
            }
        }

        return null;
    }

    private function bounded(
        mixed $value,
        float $maximum = 1000
    ): ?float {
        if (
            ! is_numeric($value)
            || ! is_finite((float) $value)
            || (float) $value < 0
            || (float) $value > $maximum
        ) {
            return null;
        }

        return round((float) $value, 2);
    }

    private function string(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim(HtmlText::decode($value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    private function stringId(mixed $value): ?string
    {
        if (! is_numeric($value) && ! is_string($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }
}
