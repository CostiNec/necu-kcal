<?php

namespace App\Services\OpenFoodFacts;

class ProductMapper
{
    /**
     * @param  array<string, mixed>  $product
     * @return array{
     *     external_id: string,
     *     food: array<string, mixed>,
     *     translations: array<string, string>,
     *     markets: array<int, string>,
     *     stores: array<int, array{external_key: string, name: string}>,
     *     schema_version: int|null
     * }|null
     */
    public function map(array $product, string $scope): ?array
    {
        $externalId = $this->string($product['code'] ?? null, 64);
        $romanianName = $this->string($product['product_name_ro'] ?? null);
        $englishName = $this->string($product['product_name_en'] ?? null);
        $name = $this->string($product['product_name'] ?? null)
            ?? $romanianName
            ?? $englishName
            ?? $this->string($product['generic_name'] ?? null);

        if ($externalId === null || $name === null) {
            return null;
        }

        $nutriments = is_array($product['nutriments'] ?? null)
            ? $product['nutriments']
            : [];
        $calories = $this->nutrient($nutriments, 'energy-kcal_100g', 10000);

        if ($calories === null) {
            $kilojoules = $this->nutrient(
                $nutriments,
                'energy-kj_100g',
                50000
            );
            $calories = $kilojoules === null
                ? null
                : round($kilojoules / 4.184, 2);
        }

        if ($calories === null) {
            return null;
        }

        if ($this->isVolumeProduct($product)) {
            return null;
        }

        $markets = $this->markets($product);

        if ($scope === 'ro' && ! in_array('RO', $markets, true)) {
            return null;
        }

        $protein = $this->nutrient($nutriments, 'proteins_100g');
        $carbohydrates = $this->nutrient(
            $nutriments,
            'carbohydrates_100g'
        );
        $fat = $this->nutrient($nutriments, 'fat_100g');
        $fibre = $this->nutrient($nutriments, 'fiber_100g');
        $brand = $this->string($product['brands'] ?? null);
        $mainLocale = $this->locale(
            $product['lang'] ?? $product['lc'] ?? null
        );
        $translations = array_filter([
            'ro' => $romanianName,
            'en' => $englishName,
        ]);

        if (
            in_array($mainLocale, ['ro', 'en'], true)
            && isset($translations[$mainLocale]) === false
        ) {
            $translations[$mainLocale] = $name;
        }

        $package = $this->quantity(
            $product['product_quantity'] ?? null,
            $product['product_quantity_unit'] ?? null
        );
        $searchText = collect([
            $name,
            $romanianName,
            $englishName,
            $this->string($product['generic_name'] ?? null),
            $this->string($product['generic_name_ro'] ?? null),
            $this->string($product['generic_name_en'] ?? null),
            $brand,
            $externalId,
        ])->filter()->unique()->implode(' ');

        return [
            'external_id' => $externalId,
            'food' => [
                'user_id' => null,
                'external_id' => $externalId,
                'food_type' => 'product',
                'name' => $name,
                'brand' => $brand,
                'main_locale' => $mainLocale,
                'barcode' => $externalId,
                'calories' => $calories,
                'protein' => $protein,
                'carbohydrates' => $carbohydrates,
                'fat' => $fat,
                'saturated_fat' => $this->nutrient(
                    $nutriments,
                    'saturated-fat_100g'
                ),
                'fibre' => $fibre,
                'sugar' => $this->nutrient($nutriments, 'sugars_100g'),
                'sodium' => $this->nutrient($nutriments, 'sodium_100g'),
                'salt' => $this->nutrient($nutriments, 'salt_100g'),
                'package_quantity' => $package['amount'] ?? null,
                'package_unit' => $package['unit'] ?? null,
                'is_public' => true,
                'nutrition_complete' => $protein !== null
                    && $carbohydrates !== null
                    && $fat !== null,
                'is_active' => true,
                'data_completeness' => $this->completeness(
                    $product['completeness'] ?? null
                ),
                'popularity_score' => $this->positiveInteger(
                    $product['unique_scans_n']
                        ?? $product['scans_n']
                        ?? null
                ),
                'image_url' => $this->url(
                    $product['image_front_small_url']
                        ?? $product['image_front_url']
                        ?? null
                ),
                'search_text' => $searchText,
                'source_updated_at' => $this->timestamp(
                    $product['last_modified_t'] ?? null
                ),
            ],
            'translations' => $translations,
            'markets' => $markets,
            'stores' => $this->stores($product),
            'schema_version' => $this->positiveInteger(
                $product['schema_version'] ?? null,
                null
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $nutriments
     */
    private function nutrient(
        array $nutriments,
        string $key,
        float $maximum = 1000
    ): ?float {
        $value = $this->number($nutriments[$key] ?? null);

        if ($value === null || $value < 0 || $value > $maximum) {
            return null;
        }

        return round($value, 2);
    }

    /**
     * @return array{amount: float, unit: string}|null
     */
    private function quantity(mixed $amount, mixed $unit): ?array
    {
        $amount = $this->number($amount);
        $unit = strtolower(trim((string) $unit));

        if ($amount === null || $amount <= 0) {
            return null;
        }

        [$amount, $unit] = match ($unit) {
            'kg' => [$amount * 1000, 'g'],
            'mg' => [$amount / 1000, 'g'],
            'g' => [$amount, $unit],
            default => [null, null],
        };

        if ($amount === null || $amount > 9999999) {
            return null;
        }

        return [
            'amount' => round($amount, 3),
            'unit' => $unit,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function isVolumeProduct(array $product): bool
    {
        $volumeUnits = ['ml', 'cl', 'dl', 'l'];
        $declaredUnits = [
            $product['product_quantity_unit'] ?? null,
            $product['serving_quantity_unit'] ?? null,
        ];

        foreach ($declaredUnits as $unit) {
            if (
                is_string($unit)
                && in_array(mb_strtolower(trim($unit)), $volumeUnits, true)
            ) {
                return true;
            }
        }

        $quantity = $product['quantity'] ?? null;

        if (! is_string($quantity)) {
            return false;
        }

        if (preg_match('/(?:ml|cl|dl)\s*$/iu', trim($quantity))) {
            return true;
        }

        return (bool) preg_match('/(?:^|[\d\s.,])l\s*$/iu', trim($quantity));
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, string>
     */
    private function markets(array $product): array
    {
        $tags = collect(
            is_array($product['countries_tags'] ?? null)
                ? $product['countries_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)))
            ->all();

        return collect(config('open-food-facts.market_tags', []))
            ->filter(
                fn (array $marketTags) => collect($marketTags)
                    ->map(fn (string $tag) => mb_strtolower($tag))
                    ->intersect($tags)
                    ->isNotEmpty()
            )
            ->keys()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, array{external_key: string, name: string}>
     */
    private function stores(array $product): array
    {
        return collect(
            is_array($product['stores_tags'] ?? null)
                ? $product['stores_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(function (string $tag): ?array {
                $externalKey = $this->string(
                    mb_strtolower(trim($tag)),
                    128
                );

                if ($externalKey === null) {
                    return null;
                }

                $name = preg_replace('/^[a-z]{2}:/i', '', $externalKey);
                $name = str_replace(['-', '_'], ' ', (string) $name);
                $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

                return [
                    'external_key' => $externalKey,
                    'name' => $this->string($name) ?? $externalKey,
                ];
            })
            ->filter()
            ->unique('external_key')
            ->values()
            ->all();
    }

    private function locale(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = mb_strtolower(trim($value));

        return preg_match('/^[a-z]{2}$/', $value) ? $value : null;
    }

    private function string(mixed $value, int $maximum = 255): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $maximum);
    }

    private function url(mixed $value): ?string
    {
        $value = $this->string($value, 2048);

        return $value !== null && filter_var($value, FILTER_VALIDATE_URL)
            ? $value
            : null;
    }

    private function number(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (float) $value;

        return is_finite($value) ? $value : null;
    }

    private function completeness(mixed $value): ?float
    {
        $value = $this->number($value);

        if ($value === null || $value < 0 || $value > 1) {
            return null;
        }

        return round($value, 4);
    }

    private function positiveInteger(
        mixed $value,
        ?int $default = 0
    ): ?int {
        if (! is_numeric($value) || (float) $value < 0) {
            return $default;
        }

        return (int) min((float) $value, PHP_INT_MAX);
    }

    private function timestamp(mixed $value): ?string
    {
        $timestamp = $this->positiveInteger($value, null);

        return $timestamp === null || $timestamp === 0
            ? null
            : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
