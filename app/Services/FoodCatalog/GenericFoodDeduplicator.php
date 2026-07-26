<?php

namespace App\Services\FoodCatalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenericFoodDeduplicator
{
    /**
     * @param  callable(array<string, int>): void|null  $progress
     * @return array{foods_scanned: int, duplicate_groups: int, foods_linked: int}
     */
    public function deduplicate(
        bool $dryRun = false,
        ?callable $progress = null
    ): array {
        $sourceRanks = $this->sourceRanks();
        $groups = [];
        $stats = [
            'foods_scanned' => 0,
            'duplicate_groups' => 0,
            'foods_linked' => 0,
        ];

        DB::table('foods')
            ->where('food_type', 'generic')
            ->where('is_active', true)
            ->where('is_public', true)
            ->whereNull('user_id')
            ->whereNull('canonical_food_id')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->orderBy('id')
            ->select([
                'id',
                'source_id',
                'name',
                'nutrition_basis_amount',
                'nutrition_basis_unit',
                'nutrition_complete',
                'is_common',
                'calories',
                'protein',
                'carbohydrates',
                'fat',
                'fibre',
                'sugar',
                'sodium',
            ])
            ->lazyById(1000)
            ->each(function (object $food) use (
                &$groups,
                &$stats,
                $sourceRanks
            ): void {
                $stats['foods_scanned']++;
                $key = $this->groupKey($food);

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'winner' => $food,
                        'loser_ids' => [],
                    ];

                    return;
                }

                $winner = $groups[$key]['winner'];

                if (
                    $this->score($food, $sourceRanks)
                    < $this->score($winner, $sourceRanks)
                ) {
                    $groups[$key]['loser_ids'][] = (int) $winner->id;
                    $groups[$key]['winner'] = $food;
                } else {
                    $groups[$key]['loser_ids'][] = (int) $food->id;
                }
            });

        foreach ($groups as $group) {
            $loserIds = array_values(array_unique($group['loser_ids']));

            if ($loserIds === []) {
                continue;
            }

            $stats['duplicate_groups']++;
            $stats['foods_linked'] += count($loserIds);

            if (! $dryRun) {
                $this->linkGroup(
                    (int) $group['winner']->id,
                    $loserIds
                );
            }

            if ($stats['duplicate_groups'] % 250 === 0) {
                $progress?->__invoke($stats);
            }
        }

        $progress?->__invoke($stats);

        return $stats;
    }

    /**
     * @return array<int, int>
     */
    private function sourceRanks(): array
    {
        $order = [
            'curated_common',
            'fineli',
            'usda_food_data_central',
            'canadian_nutrient_file',
            'cofid',
            'afcd',
        ];
        $codes = DB::table('food_sources')
            ->whereIn('code', $order)
            ->pluck('id', 'code');

        return collect($order)
            ->mapWithKeys(fn (string $code, int $rank) => [
                (int) $codes->get($code, 0) => $rank,
            ])
            ->forget(0)
            ->all();
    }

    private function groupKey(object $food): string
    {
        return implode('|', [
            $this->normalize((string) $food->name),
            strtolower((string) $food->nutrition_basis_unit),
            number_format(
                (float) $food->nutrition_basis_amount,
                3,
                '.',
                ''
            ),
        ]);
    }

    /**
     * @param  array<int, int>  $sourceRanks
     * @return array<int, int>
     */
    private function score(object $food, array $sourceRanks): array
    {
        $nutrients = [
            'calories',
            'protein',
            'carbohydrates',
            'fat',
            'fibre',
            'sugar',
            'sodium',
        ];
        $nutrientCount = collect($nutrients)
            ->filter(fn (string $column) => $food->{$column} !== null)
            ->count();

        return [
            $food->is_common ? 0 : 1,
            $sourceRanks[(int) $food->source_id] ?? 50,
            $food->nutrition_complete ? 0 : 1,
            -$nutrientCount,
            (int) $food->id,
        ];
    }

    /**
     * @param  array<int, int>  $loserIds
     */
    private function linkGroup(int $winnerId, array $loserIds): void
    {
        DB::transaction(function () use ($winnerId, $loserIds): void {
            $names = DB::table('food_translations')
                ->whereIn('food_id', $loserIds)
                ->get(['locale', 'name'])
                ->concat(
                    DB::table('food_aliases')
                        ->whereIn('food_id', $loserIds)
                        ->get(['locale', 'name'])
                )
                ->filter(fn (object $name) => trim($name->name) !== '')
                ->unique(
                    fn (object $name) => $name->locale."\0".$name->name
                )
                ->values();
            $now = now();

            if ($names->isNotEmpty()) {
                DB::table('food_aliases')->insertOrIgnore(
                    $names
                        ->map(fn (object $name) => [
                            'food_id' => $winnerId,
                            'locale' => $name->locale,
                            'name' => $name->name,
                            'alias_type' => 'source_duplicate',
                            'priority' => 80,
                            'source' => 'generic_deduplication',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->all()
                );
            }

            $searchText = DB::table('foods')
                ->where('id', $winnerId)
                ->value('search_text');
            $searchText = collect([
                $searchText,
                ...$names->pluck('name'),
            ])
                ->filter()
                ->unique(fn (string $name) => $this->normalize($name))
                ->implode(' ');

            DB::table('foods')
                ->where('id', $winnerId)
                ->update([
                    'search_text' => $searchText,
                    'updated_at' => $now,
                ]);

            DB::table('foods')
                ->whereIn('id', $loserIds)
                ->whereNull('canonical_food_id')
                ->update([
                    'canonical_food_id' => $winnerId,
                    'updated_at' => $now,
                ]);
        });
    }

    private function normalize(string $value): string
    {
        return preg_replace(
            '/\s+/u',
            ' ',
            Str::lower(Str::ascii(trim($value)))
        ) ?? '';
    }
}
