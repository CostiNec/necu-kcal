<?php

namespace App\Services\FoodCatalog;

use Illuminate\Support\Facades\DB;

class FoodRecordPersister
{
    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{inserted: int, updated: int}
     */
    public function persist(
        array $records,
        int $sourceId,
        string $sourceCode
    ): array {
        $records = collect($records)
            ->keyBy('external_id')
            ->values();
        $externalIds = $records->pluck('external_id')->all();
        $existing = DB::table('foods')
            ->where('source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->all();
        $now = now();
        $rows = $records->map(fn (array $record) => [
            ...$record['food'],
            'source_id' => $sourceId,
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('foods')->upsert(
            $rows,
            ['source_id', 'external_id'],
            [
                'food_type',
                'search_priority',
                'name',
                'main_locale',
                'calories',
                'nutrition_basis_amount',
                'nutrition_basis_unit',
                'protein',
                'carbohydrates',
                'fat',
                'saturated_fat',
                'fibre',
                'sugar',
                'sodium',
                'salt',
                'nutrition_complete',
                'is_active',
                'search_text',
                'source_updated_at',
                'imported_at',
                'updated_at',
            ]
        );

        $foodIds = DB::table('foods')
            ->where('source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id');
        $translations = [];
        $aliases = [];

        foreach ($records as $record) {
            $foodId = $foodIds->get($record['external_id']);

            if ($foodId === null) {
                continue;
            }

            foreach ($record['translations'] ?? [] as $locale => $name) {
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                $translations[] = [
                    'food_id' => $foodId,
                    'locale' => $locale,
                    'name' => $name,
                    'translation_source' => $sourceCode,
                    'reviewed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($record['aliases'] ?? [] as $alias) {
                if (
                    ! is_string($alias['name'] ?? null)
                    || trim($alias['name']) === ''
                ) {
                    continue;
                }

                $aliases[] = [
                    'food_id' => $foodId,
                    'locale' => $alias['locale'] ?? 'en',
                    'name' => $alias['name'],
                    'alias_type' => $alias['alias_type'] ?? 'synonym',
                    'priority' => $alias['priority'] ?? 100,
                    'source' => $alias['source'] ?? $sourceCode,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($translations !== []) {
            DB::table('food_translations')->upsert(
                $translations,
                ['food_id', 'locale'],
                [
                    'name',
                    'translation_source',
                    'updated_at',
                ]
            );
        }

        if ($aliases !== []) {
            DB::table('food_aliases')->upsert(
                $aliases,
                ['food_id', 'locale', 'name'],
                [
                    'alias_type',
                    'priority',
                    'source',
                    'updated_at',
                ]
            );
        }

        return [
            'inserted' => count($rows) - count($existing),
            'updated' => count($existing),
        ];
    }
}
