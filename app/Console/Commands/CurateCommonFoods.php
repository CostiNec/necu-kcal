<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CurateCommonFoods extends Command
{
    protected $signature = 'foods:curate-common
        {--dry-run : Show matches without writing}
        {--link-exact-duplicates : Hide imported rows with an exact canonical name or alias}';

    protected $description =
        'Build stable EN/RO common foods from imported nutrition records';

    public function handle(): int
    {
        try {
            $sourceId = DB::table('food_sources')
                ->where('code', 'curated_common')
                ->value('id');

            if ($sourceId === null) {
                throw new RuntimeException(
                    'The curated common source is missing. Run migrations first.'
                );
            }

            $matched = 0;
            $missing = [];

            foreach (config('common-foods', []) as $priority => $item) {
                $source = $this->findNutritionSource($item);

                if ($source === null) {
                    $missing[] = $item['en'];
                    $this->warn("No nutrition source: {$item['en']}");

                    continue;
                }

                $matched++;
                $this->line(sprintf(
                    '%s ← %s #%s',
                    $item['en'],
                    $source->name,
                    $source->id
                ));

                if ($this->option('dry-run')) {
                    continue;
                }

                DB::transaction(function () use (
                    $sourceId,
                    $source,
                    $item,
                    $priority
                ): void {
                    $canonical = $this->upsertCanonical(
                        (int) $sourceId,
                        $source,
                        $item,
                        $priority
                    );
                    $this->upsertNames($canonical, $item);

                    if ($this->option('link-exact-duplicates')) {
                        $this->linkExactDuplicates($canonical, $item);
                    }
                });
            }

            $this->newLine();
            $this->components->info(sprintf(
                'Matched %d common foods; %d need review.',
                $matched,
                count($missing)
            ));

            if ($missing !== []) {
                $this->line('Missing: '.implode(', ', $missing));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function findNutritionSource(array $item): ?Food
    {
        $sourceOrder = [
            'fineli' => 0,
            'usda_food_data_central' => 1,
            'canadian_nutrient_file' => 2,
            'cofid' => 3,
            'afcd' => 4,
        ];
        $sourceIds = DB::table('food_sources')
            ->whereIn('code', array_keys($sourceOrder))
            ->pluck('code', 'id');
        $candidates = collect($item['candidates'] ?? [$item['en']])
            ->filter()
            ->values();
        $foods = collect();

        foreach ($candidates as $candidateIndex => $candidate) {
            $matches = Food::query()
                ->where('food_type', 'generic')
                ->where('is_active', true)
                ->whereIn('source_id', $sourceIds->keys())
                ->where('name', 'like', $this->escapeLike($candidate).'%')
                ->limit(30)
                ->get()
                ->map(function (Food $food) use (
                    $candidate,
                    $candidateIndex,
                    $sourceIds,
                    $sourceOrder
                ) {
                    $normalizedName = $this->normalize($food->name);
                    $normalizedCandidate = $this->normalize($candidate);
                    $sourceCode = $sourceIds->get($food->source_id);
                    $food->setAttribute('_curation_score', [
                        $normalizedName === $normalizedCandidate ? 0 : 1,
                        $candidateIndex,
                        $sourceOrder[$sourceCode] ?? 99,
                        mb_strlen($food->name),
                        $food->id,
                    ]);

                    return $food;
                });

            $foods = $foods->concat($matches);
        }

        return $foods
            ->unique('id')
            ->sortBy(fn (Food $food) => $food->getAttribute(
                '_curation_score'
            ))
            ->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertCanonical(
        int $sourceId,
        Food $source,
        array $item,
        int $priority
    ): Food {
        $columns = [
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
        ];
        $nutrition = collect($columns)
            ->mapWithKeys(fn (string $column) => [
                $column => $source->getRawOriginal($column),
            ])
            ->all();
        $searchText = collect([
            $item['en'],
            $item['ro'],
            ...($item['en_aliases'] ?? []),
            ...($item['ro_aliases'] ?? []),
        ])->filter()->unique()->implode(' ');

        $canonical = Food::query()->firstOrNew([
            'source_id' => $sourceId,
            'external_id' => $item['key'],
        ]);
        $canonical->forceFill([
            'user_id' => null,
            'source_id' => $sourceId,
            'external_id' => $item['key'],
            'nutrition_source_food_id' => $source->id,
            'food_type' => 'generic',
            'search_priority' => 0,
            'is_common' => true,
            'common_priority' => $priority + 1,
            'name' => $item['en'],
            'brand' => null,
            'main_locale' => 'en',
            'barcode' => null,
            ...$nutrition,
            'is_public' => true,
            'nutrition_complete' => $source->nutrition_complete,
            'is_active' => true,
            'popularity_score' => $source->popularity_score,
            'search_text' => $searchText,
            'imported_at' => now(),
        ]);
        $canonical->save();

        return $canonical;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertNames(Food $canonical, array $item): void
    {
        $now = now();
        $translations = [
            [
                'food_id' => $canonical->id,
                'locale' => 'en',
                'name' => $item['en'],
                'translation_source' => 'curated_common',
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'food_id' => $canonical->id,
                'locale' => 'ro',
                'name' => $item['ro'],
                'translation_source' => 'curated_common',
                'reviewed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        DB::table('food_translations')->upsert(
            $translations,
            ['food_id', 'locale'],
            [
                'name',
                'translation_source',
                'reviewed_at',
                'updated_at',
            ]
        );

        $aliases = [];

        foreach (['en', 'ro'] as $locale) {
            foreach ($item[$locale.'_aliases'] ?? [] as $alias) {
                $aliases[] = [
                    'food_id' => $canonical->id,
                    'locale' => $locale,
                    'name' => $alias,
                    'alias_type' => 'curated_synonym',
                    'priority' => 10,
                    'source' => 'curated_common',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($aliases !== []) {
            DB::table('food_aliases')->upsert(
                $aliases,
                ['food_id', 'locale', 'name'],
                ['alias_type', 'priority', 'source', 'updated_at']
            );
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function linkExactDuplicates(
        Food $canonical,
        array $item
    ): void {
        $names = collect([
            $item['en'],
            $item['ro'],
            ...($item['en_aliases'] ?? []),
            ...($item['ro_aliases'] ?? []),
        ])->filter()->unique()->values();

        Food::query()
            ->whereKeyNot($canonical->id)
            ->where('food_type', 'generic')
            ->whereNull('canonical_food_id')
            ->whereIn('name', $names)
            ->update(['canonical_food_id' => $canonical->id]);

        $translatedIds = DB::table('food_translations')
            ->whereIn('name', $names)
            ->pluck('food_id');

        if ($translatedIds->isNotEmpty()) {
            Food::query()
                ->whereKeyNot($canonical->id)
                ->where('food_type', 'generic')
                ->whereNull('canonical_food_id')
                ->whereIn('id', $translatedIds)
                ->update(['canonical_food_id' => $canonical->id]);
        }
    }

    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value
        );
    }
}
