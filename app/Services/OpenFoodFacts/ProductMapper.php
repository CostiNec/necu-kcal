<?php

namespace App\Services\OpenFoodFacts;

use App\Support\HtmlText;

class ProductMapper
{
    public const SKIP_MISSING_SOURCE_ID = 'missing_source_id';

    public const SKIP_MISSING_NAME = 'missing_name';

    public const SKIP_MISSING_ENERGY = 'missing_energy';

    public const SKIP_OUTSIDE_SCOPE = 'outside_scope';

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
        return $this->mapWithReason($product, $scope)['product'];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array{
     *     product: array{
     *         external_id: string,
     *         food: array<string, mixed>,
     *         translations: array<string, string>,
     *         markets: array<int, string>,
     *         stores: array<int, array{external_key: string, name: string}>,
     *         schema_version: int|null
     *     }|null,
     *     skipped_reason: string|null
     * }
     */
    public function mapWithReason(array $product, string $scope): array
    {
        $barcode = $this->string($product['code'] ?? null, 64);
        $externalId = $barcode
            ?? $this->string($product['_id'] ?? $product['id'] ?? null, 64);

        if ($externalId === null) {
            return $this->skipped(self::SKIP_MISSING_SOURCE_ID);
        }

        $romanianName = $this->string($product['product_name_ro'] ?? null);
        $englishName = $this->string($product['product_name_en'] ?? null);
        $name = $this->string($product['product_name'] ?? null)
            ?? $romanianName
            ?? $englishName
            ?? $this->string($product['generic_name'] ?? null);

        if ($name === null) {
            return $this->skipped(self::SKIP_MISSING_NAME);
        }

        $nutriments = is_array($product['nutriments'] ?? null)
            ? $product['nutriments']
            : [];
        $isPlainWater = $this->isPlainWater($product);
        $isZeroCalorieTea = $this->isZeroCalorieTea(
            $product,
            $nutriments
        );
        $aggregatedNutrition = $this->aggregatedNutrition($product)
            ?? $this->inputSetNutrition($product);
        $preparedNutrition = $this->aggregatedNutrition($product, 'prepared')
            ?? $this->inputSetNutrition($product, 'prepared');
        $directNutrition = $this->directNutrition($product, $nutriments);
        $legacyBasisUnit = $this->isVolumeProduct($product) ? 'ml' : 'g';
        $calories = $this->nutrient(
            $nutriments,
            'energy-kcal_100g',
            10000
        );

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

        $nutritionBasisUnit = $legacyBasisUnit;

        if ($calories === null && $aggregatedNutrition !== null) {
            $calories = $this->aggregatedNutrient(
                $aggregatedNutrition,
                'energy-kcal',
                'kcal',
                10000
            );

            if ($calories === null) {
                $kilojoules = $this->aggregatedNutrient(
                    $aggregatedNutrition,
                    ['energy-kj', 'energy'],
                    'kj',
                    50000
                );
                $calories = $kilojoules === null
                    ? null
                    : round($kilojoules / 4.184, 2);
            }

            if ($calories !== null) {
                $nutritionBasisUnit = $aggregatedNutrition['basis_unit'];
            }
        }

        if ($calories === null && $directNutrition !== null) {
            $calories = $this->directNutrient(
                $directNutrition,
                'energy-kcal',
                10000
            );

            if ($calories === null) {
                $kilojoules = $this->directNutrient(
                    $directNutrition,
                    ['energy-kj', 'energy'],
                    50000
                );
                $calories = $kilojoules === null
                    ? null
                    : round($kilojoules / 4.184, 2);
            }

            if ($calories !== null) {
                $nutritionBasisUnit = $directNutrition['basis_unit'];
            }
        }

        if ($calories === null) {
            $estimatedEnergy = $this->estimatedEnergyFromMacros(
                $nutriments,
                $aggregatedNutrition,
                $directNutrition,
                $legacyBasisUnit
            );

            if ($estimatedEnergy !== null) {
                $calories = $estimatedEnergy['calories'];
                $nutritionBasisUnit = $estimatedEnergy['basis_unit'];
            }
        }

        if ($calories === null && $preparedNutrition !== null) {
            $calories = $this->aggregatedNutrient(
                $preparedNutrition,
                'energy-kcal',
                'kcal',
                10000
            );

            if ($calories === null) {
                $kilojoules = $this->aggregatedNutrient(
                    $preparedNutrition,
                    ['energy-kj', 'energy'],
                    'kj',
                    50000
                );
                $calories = $kilojoules === null
                    ? null
                    : round($kilojoules / 4.184, 2);
            }

            if ($calories === null) {
                $estimatedEnergy = $this->estimatedEnergyFromMacros(
                    [],
                    $preparedNutrition,
                    null,
                    $preparedNutrition['basis_unit']
                );
                $calories = $estimatedEnergy['calories'] ?? null;
            }

            if ($calories !== null) {
                $nutritionBasisUnit = $preparedNutrition['basis_unit'];
                $aggregatedNutrition = $preparedNutrition;
            }
        }

        if ($calories === null && ($isPlainWater || $isZeroCalorieTea)) {
            $calories = 0.0;
            $nutritionBasisUnit = $aggregatedNutrition['basis_unit']
                ?? $directNutrition['basis_unit']
                ?? $legacyBasisUnit;
        }

        if ($calories === null) {
            if (!in_array($product['_id'], ['0000130008136','0000140323687','0000141013129','0011110090904']) && strpos('missing energy_100g', $product['nutrition_score_debug']) !== false) {
                dd($product);
            }
            return $this->skipped(self::SKIP_MISSING_ENERGY);
        }

        $compatibleAggregatedNutrition =
            $aggregatedNutrition !== null
            && $aggregatedNutrition['basis_unit'] === $nutritionBasisUnit
                ? $aggregatedNutrition
                : null;
        $compatibleDirectNutrition =
            $directNutrition !== null
            && $directNutrition['basis_unit'] === $nutritionBasisUnit
                ? $directNutrition
                : null;

        $markets = $this->markets($product);

        if ($scope === 'ro' && ! in_array('RO', $markets, true)) {
            return $this->skipped(self::SKIP_OUTSIDE_SCOPE);
        }

        $protein = $this->nutrient($nutriments, 'proteins_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                'proteins'
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                'proteins'
            );
        $carbohydrates = $this->nutrient(
            $nutriments,
            'carbohydrates_100g'
        ) ?? $this->aggregatedNutrient(
            $compatibleAggregatedNutrition,
            'carbohydrates'
        ) ?? $this->directNutrient(
            $compatibleDirectNutrition,
            'carbohydrates'
        );
        $fat = $this->nutrient($nutriments, 'fat_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                'fat'
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                'fat'
            );
        $fibre = $this->nutrient($nutriments, 'fiber_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                ['fiber', 'fibre']
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                ['fiber', 'fibre']
            );
        $saturatedFat = $this->nutrient(
            $nutriments,
            'saturated-fat_100g'
        ) ?? $this->aggregatedNutrient(
            $compatibleAggregatedNutrition,
            'saturated-fat'
        ) ?? $this->directNutrient(
            $compatibleDirectNutrition,
            'saturated-fat'
        );
        $sugar = $this->nutrient($nutriments, 'sugars_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                'sugars'
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                'sugars'
            );
        $sodium = $this->nutrient($nutriments, 'sodium_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                'sodium'
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                'sodium'
            );
        $salt = $this->nutrient($nutriments, 'salt_100g')
            ?? $this->aggregatedNutrient(
                $compatibleAggregatedNutrition,
                'salt'
            )
            ?? $this->directNutrient(
                $compatibleDirectNutrition,
                'salt'
            );

        if ($isPlainWater || $isZeroCalorieTea) {
            $protein ??= 0.0;
            $carbohydrates ??= 0.0;
            $fat ??= 0.0;
            $fibre ??= 0.0;
            $saturatedFat ??= 0.0;
            $sugar ??= 0.0;
            $sodium ??= 0.0;
            $salt ??= 0.0;
        }

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
            $barcode,
        ])->filter()->unique()->implode(' ');

        return [
            'product' => [
                'external_id' => $externalId,
                'food' => [
                    'user_id' => null,
                    'external_id' => $externalId,
                    'food_type' => 'product',
                    'search_priority' => 2,
                    'name' => $name,
                    'brand' => $brand,
                    'main_locale' => $mainLocale,
                    'barcode' => $barcode,
                    'calories' => $calories,
                    'nutrition_basis_amount' => 100,
                    'nutrition_basis_unit' => $nutritionBasisUnit,
                    'protein' => $protein,
                    'carbohydrates' => $carbohydrates,
                    'fat' => $fat,
                    'saturated_fat' => $saturatedFat,
                    'fibre' => $fibre,
                    'sugar' => $sugar,
                    'sodium' => $sodium,
                    'salt' => $salt,
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
     * @param  array<string, mixed>  $product
     * @return array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null
     */
    private function aggregatedNutrition(
        array $product,
        string $acceptedPreparation = 'as_sold'
    ): ?array {
        $nutrition = $product['nutrition'] ?? null;

        if (! is_array($nutrition)) {
            return null;
        }

        $set = $nutrition['aggregated_set'] ?? null;

        if (! is_array($set)) {
            return null;
        }

        $preparation = is_string($set['preparation'] ?? null)
            ? mb_strtolower(trim($set['preparation']))
            : null;

        if ($acceptedPreparation === 'as_sold') {
            if ($preparation !== null && $preparation !== 'as_sold') {
                return null;
            }
        } elseif ($preparation !== $acceptedPreparation) {
            return null;
        }

        $per = is_string($set['per'] ?? null)
            ? mb_strtolower(
                preg_replace('/\s+/u', '', $set['per']) ?? ''
            )
            : '';
        $basisUnit = match ($per) {
            '100g' => 'g',
            '100ml' => 'ml',
            default => null,
        };

        if ($basisUnit === null) {
            return null;
        }

        $nutrients = $set['nutrients'] ?? null;

        if (! is_array($nutrients)) {
            return null;
        }

        return [
            'basis_unit' => $basisUnit,
            'nutrients' => $nutrients,
        ];
    }

    /**
     * Merge complementary input sets for one preparation after normalizing
     * each to a 100g or 100ml basis. Direct per-100 packaging data has the
     * highest priority.
     *
     * @param  array<string, mixed>  $product
     * @return array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null
     */
    private function inputSetNutrition(
        array $product,
        string $preparation = 'as_sold'
    ): ?array {
        $nutrition = $product['nutrition'] ?? null;

        if (! is_array($nutrition)) {
            return null;
        }

        $inputSets = $nutrition['input_sets'] ?? null;

        if (! is_array($inputSets)) {
            return null;
        }

        $candidates = [];

        foreach ($inputSets as $index => $inputSet) {
            if (! is_array($inputSet)) {
                continue;
            }

            $candidate = $this->normalizeInputSet(
                $inputSet,
                $preparation
            );

            if ($candidate === null) {
                continue;
            }

            $candidate['index'] = is_int($index) ? $index : 0;
            $candidates[] = $candidate;
        }

        if ($candidates === []) {
            return null;
        }

        $preferredBasis = $this->isVolumeProduct($product) ? 'ml' : 'g';
        $preferredCandidates = array_values(array_filter(
            $candidates,
            fn (array $candidate) => $candidate['basis_unit']
                === $preferredBasis
        ));

        if ($preferredCandidates !== []) {
            $candidates = $preferredCandidates;
        } else {
            $basisUnits = array_values(array_unique(array_column(
                $candidates,
                'basis_unit'
            )));

            if (count($basisUnits) !== 1) {
                return null;
            }
        }

        usort(
            $candidates,
            fn (array $left, array $right) => ($right['priority'] <=> $left['priority'])
                ?: ($right['updated_at'] <=> $left['updated_at'])
                ?: ($right['index'] <=> $left['index'])
        );

        $nutrients = [];

        foreach ($candidates as $candidate) {
            foreach ($candidate['nutrients'] as $key => $nutrient) {
                if (
                    ! is_string($key)
                    || ! is_array($nutrient)
                    || array_key_exists($key, $nutrients)
                ) {
                    continue;
                }

                $nutrients[$key] = $nutrient;
            }
        }

        if ($nutrients === []) {
            return null;
        }

        return [
            'basis_unit' => $candidates[0]['basis_unit'],
            'nutrients' => $nutrients,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputSet
     * @return array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>,
     *     priority: int,
     *     updated_at: int
     * }|null
     */
    private function normalizeInputSet(
        array $inputSet,
        string $acceptedPreparation
    ): ?array {
        $preparation = is_string($inputSet['preparation'] ?? null)
            ? mb_strtolower(trim($inputSet['preparation']))
            : null;

        if ($preparation !== $acceptedPreparation) {
            return null;
        }

        $per = is_string($inputSet['per'] ?? null)
            ? mb_strtolower(
                preg_replace('/\s+/u', '', $inputSet['per']) ?? ''
            )
            : '';
        $factor = 1.0;
        $basisUnit = match ($per) {
            '100g' => 'g',
            '100ml' => 'ml',
            default => null,
        };
        $isDirectPerHundred = $basisUnit !== null;

        if ($per === 'serving') {
            $quantity = $this->quantity(
                $inputSet['per_quantity'] ?? null,
                $inputSet['per_unit'] ?? null
            );

            if (
                $quantity === null
                || ! in_array($quantity['unit'], ['g', 'ml'], true)
            ) {
                return null;
            }

            $basisUnit = $quantity['unit'];
            $factor = 100 / $quantity['amount'];
        }

        if ($basisUnit === null || ! is_finite($factor) || $factor <= 0) {
            return null;
        }

        $source = is_string($inputSet['source'] ?? null)
            ? mb_strtolower(trim($inputSet['source']))
            : null;
        $priority = ($source === 'packaging' ? 20 : 0)
            + ($isDirectPerHundred ? 10 : 0);
        $rawNutrients = $inputSet['nutrients'] ?? null;

        if (! is_array($rawNutrients)) {
            return null;
        }

        $nutrients = [];

        foreach ($rawNutrients as $key => $nutrient) {
            if (! is_string($key) || ! is_array($nutrient)) {
                continue;
            }

            $scaledNutrient = $nutrient;

            foreach (['value', 'value_computed'] as $valueKey) {
                $value = $this->number($nutrient[$valueKey] ?? null);

                if ($value !== null) {
                    $scaledNutrient[$valueKey] = $value * $factor;
                }
            }

            $nutrients[$key] = $scaledNutrient;
        }

        return [
            'basis_unit' => $basisUnit,
            'nutrients' => $nutrients,
            'priority' => $priority,
            'updated_at' => $this->positiveInteger(
                $inputSet['last_updated_t'] ?? null
            ) ?? 0,
        ];
    }

    /**
     * Older imports can contain normalized, unsuffixed nutrient values while
     * retaining the original per-100 basis in import metadata.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $nutriments
     * @return array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null
     */
    private function directNutrition(
        array $product,
        array $nutriments
    ): ?array {
        foreach (
            ['nutrition_data_per', 'nutrition_data_per_imported'] as $basisField
        ) {
            $basis = $product[$basisField] ?? null;

            if (! is_string($basis)) {
                continue;
            }

            $basis = mb_strtolower(
                preg_replace('/\s+/u', '', $basis) ?? ''
            );
            $basisUnit = match ($basis) {
                '100g' => 'g',
                '100ml' => 'ml',
                default => null,
            };

            if ($basisUnit !== null) {
                return [
                    'basis_unit' => $basisUnit,
                    'nutrients' => $nutriments,
                ];
            }
        }

        if ($this->isLegacyUsdaImport($product)) {
            return [
                'basis_unit' => 'g',
                'nutrients' => $nutriments,
            ];
        }

        return null;
    }

    /**
     * The historical USDA-NDB importer stored normalized per-100g values in
     * unsuffixed fields, including records later marked as per serving.
     *
     * @param  array<string, mixed>  $product
     */
    private function isLegacyUsdaImport(array $product): bool
    {
        $creator = is_string($product['creator'] ?? null)
            ? mb_strtolower(trim($product['creator']))
            : null;

        if ($creator === 'usda-ndb-import') {
            return true;
        }

        $sourceTags = is_array($product['data_sources_tags'] ?? null)
            ? $product['data_sources_tags']
            : [];

        foreach ($sourceTags as $sourceTag) {
            if (
                is_string($sourceTag)
                && in_array(
                    mb_strtolower(trim($sourceTag)),
                    ['database-usda-ndb', 'usda-ndb'],
                    true
                )
            ) {
                return true;
            }
        }

        $sources = is_array($product['sources'] ?? null)
            ? $product['sources']
            : [];

        foreach ($sources as $source) {
            if (! is_array($source)) {
                continue;
            }

            $sourceId = $source['id'] ?? null;

            if (
                is_string($sourceId)
                && in_array(
                    mb_strtolower(trim($sourceId)),
                    ['database-usda-ndb', 'usda-ndb'],
                    true
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null  $nutrition
     * @param  string|array<int, string>  $keys
     */
    private function directNutrient(
        ?array $nutrition,
        string|array $keys,
        float $maximum = 1000
    ): ?float {
        if ($nutrition === null) {
            return null;
        }

        foreach ((array) $keys as $key) {
            $value = $this->nutrient(
                $nutrition['nutrients'],
                $key,
                $maximum
            );

            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Estimate energy only when protein, carbohydrates, and fat are all
     * explicitly available on the same validated per-100 basis.
     *
     * @param  array<string, mixed>  $nutriments
     * @param  array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null  $aggregatedNutrition
     * @param  array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null  $directNutrition
     * @return array{calories: float, basis_unit: 'g'|'ml'}|null
     */
    private function estimatedEnergyFromMacros(
        array $nutriments,
        ?array $aggregatedNutrition,
        ?array $directNutrition,
        string $legacyBasisUnit
    ): ?array {
        $sources = [[
            'basis_unit' => $legacyBasisUnit,
            'protein' => $this->nutrient($nutriments, 'proteins_100g'),
            'carbohydrates' => $this->nutrient(
                $nutriments,
                'carbohydrates_100g'
            ),
            'fat' => $this->nutrient($nutriments, 'fat_100g'),
        ]];

        if ($aggregatedNutrition !== null) {
            $sources[] = [
                'basis_unit' => $aggregatedNutrition['basis_unit'],
                'protein' => $this->aggregatedNutrient(
                    $aggregatedNutrition,
                    'proteins'
                ),
                'carbohydrates' => $this->aggregatedNutrient(
                    $aggregatedNutrition,
                    'carbohydrates'
                ),
                'fat' => $this->aggregatedNutrient(
                    $aggregatedNutrition,
                    'fat'
                ),
            ];
        }

        if ($directNutrition !== null) {
            $sources[] = [
                'basis_unit' => $directNutrition['basis_unit'],
                'protein' => $this->directNutrient(
                    $directNutrition,
                    'proteins'
                ),
                'carbohydrates' => $this->directNutrient(
                    $directNutrition,
                    'carbohydrates'
                ),
                'fat' => $this->directNutrient($directNutrition, 'fat'),
            ];
        }

        foreach ($sources as $source) {
            if (
                $source['protein'] === null
                || $source['carbohydrates'] === null
                || $source['fat'] === null
            ) {
                continue;
            }

            $calories = ($source['protein'] * 4)
                + ($source['carbohydrates'] * 4)
                + ($source['fat'] * 9);

            if ($calories <= 0 || $calories > 10000) {
                continue;
            }

            return [
                'calories' => round($calories, 2),
                'basis_unit' => $source['basis_unit'],
            ];
        }

        return null;
    }

    /**
     * @param  array{
     *     basis_unit: 'g'|'ml',
     *     nutrients: array<string, mixed>
     * }|null  $nutrition
     * @param  string|array<int, string>  $keys
     */
    private function aggregatedNutrient(
        ?array $nutrition,
        string|array $keys,
        string $targetUnit = 'g',
        float $maximum = 1000
    ): ?float {
        if ($nutrition === null) {
            return null;
        }

        foreach ((array) $keys as $key) {
            $nutrient = $nutrition['nutrients'][$key] ?? null;

            if (! is_array($nutrient)) {
                continue;
            }

            $value = $this->number($nutrient['value'] ?? null)
                ?? $this->number($nutrient['value_computed'] ?? null);

            if ($value === null) {
                continue;
            }

            $value = $this->convertNutritionUnit(
                $value,
                $nutrient['unit'] ?? null,
                $targetUnit
            );

            if ($value === null || $value < 0 || $value > $maximum) {
                continue;
            }

            return round($value, 2);
        }

        return null;
    }

    private function convertNutritionUnit(
        float $value,
        mixed $sourceUnit,
        string $targetUnit
    ): ?float {
        if (! is_string($sourceUnit)) {
            return null;
        }

        $sourceUnit = mb_strtolower(trim($sourceUnit));
        $sourceUnit = str_replace(['μ', 'mcg'], ['µ', 'µg'], $sourceUnit);

        if ($sourceUnit === $targetUnit) {
            return $value;
        }

        if ($targetUnit !== 'g') {
            return null;
        }

        return match ($sourceUnit) {
            'mg' => $value / 1000,
            'µg', 'ug' => $value / 1000000,
            default => null,
        };
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
            'l' => [$amount * 1000, 'ml'],
            'cl' => [$amount * 10, 'ml'],
            'dl' => [$amount * 100, 'ml'],
            'ml' => [$amount, $unit],
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
     * Missing nutrition is safely zero only when the product is identified as
     * water and either contains only water/carbonation ingredients or has no
     * ingredient data while both category and Nutri-Score identify it as water.
     *
     * @param  array<string, mixed>  $product
     */
    private function isPlainWater(array $product): bool
    {
        $ingredientTags = collect(
            is_array($product['ingredients_tags'] ?? null)
                ? $product['ingredients_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)))
            ->unique()
            ->values();

        if ($ingredientTags->isEmpty()) {
            $ingredientTags = collect(
                is_array($product['ingredients'] ?? null)
                    ? $product['ingredients']
                    : []
            )
                ->map(
                    fn (mixed $ingredient) => is_array($ingredient)
                        ? $ingredient['id'] ?? null
                        : null
                )
                ->filter(fn (mixed $tag) => is_string($tag))
                ->map(fn (string $tag) => mb_strtolower(trim($tag)))
                ->unique()
                ->values();
        }

        $waterIngredients = [
            'en:water',
            'en:spring-water',
            'en:mineral-water',
            'en:natural-mineral-water',
            'en:carbonated-water',
            'en:carbonated-natural-mineral-water',
            'en:carbon-dioxide',
            'en:e290',
        ];

        if ($ingredientTags->diff($waterIngredients)->isNotEmpty()) {
            return false;
        }

        $categoryTags = collect(
            is_array($product['categories_tags'] ?? null)
                ? $product['categories_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)));
        $waterCategories = [
            'en:waters',
            'en:spring-waters',
            'en:mineral-waters',
            'en:carbonated-waters',
            'en:drinking-water',
            'en:drinking-waters',
        ];
        $isWaterCategory = $categoryTags
            ->intersect($waterCategories)
            ->isNotEmpty();
        $nutriscoreData = is_array($product['nutriscore_data'] ?? null)
            ? $product['nutriscore_data']
            : [];
        $isMarkedAsWater = in_array(
            $nutriscoreData['is_water'] ?? null,
            [true, 1, '1'],
            true
        );

        if ($ingredientTags->isEmpty()) {
            return $isWaterCategory && $isMarkedAsWater;
        }

        return $isWaterCategory || $isMarkedAsWater;
    }

    /**
     * Infer zero energy for a plain tea only when the source explicitly reports
     * all core macros as zero and classification confirms a single-ingredient,
     * unsweetened tea-bag product.
     *
     * @param  array<string, mixed>  $product
     * @param  array<string, mixed>  $nutriments
     */
    private function isZeroCalorieTea(
        array $product,
        array $nutriments
    ): bool {
        foreach (
            ['proteins_100g', 'carbohydrates_100g', 'fat_100g'] as $key
        ) {
            if ($this->nutrient($nutriments, $key) !== 0.0) {
                return false;
            }
        }

        $categoryTags = collect(
            is_array($product['categories_tags'] ?? null)
                ? $product['categories_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)));

        if (
            ! $categoryTags->contains('en:teas')
            || ! $categoryTags->contains('en:tea-bags')
        ) {
            return false;
        }

        $foodGroupTags = collect(
            is_array($product['food_groups_tags'] ?? null)
                ? $product['food_groups_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)));

        if (! $foodGroupTags->contains('en:unsweetened-beverages')) {
            return false;
        }

        $ingredientTags = collect(
            is_array($product['ingredients_tags'] ?? null)
                ? $product['ingredients_tags']
                : []
        )
            ->filter(fn (mixed $tag) => is_string($tag))
            ->map(fn (string $tag) => mb_strtolower(trim($tag)))
            ->values();
        $additiveTags = collect(
            is_array($product['additives_tags'] ?? null)
                ? $product['additives_tags']
                : []
        )->filter();

        return $ingredientTags->count() === 1
            && str_contains($ingredientTags->first(), 'tea')
            && $additiveTags->isEmpty();
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

        $value = trim(HtmlText::decode($value) ?? '');

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
