<?php

namespace App\Console\Commands;

use App\Services\FoodTranslations\FoodNameTranslator;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TranslateGenericFoods extends Command
{
    protected $signature = 'foods:translate-generics
        {--from=en : Source locale}
        {--to=ro : Target locale}
        {--batch=500 : Names loaded per translation cycle}
        {--limit= : Maximum number of foods to translate}
        {--dry-run : Count eligible foods and characters without translating}';

    protected $description = 'Add missing localized names to generic foods';

    public function handle(FoodNameTranslator $translator): int
    {
        try {
            $sourceLocale = $this->locale((string) $this->option('from'));
            $targetLocale = $this->locale((string) $this->option('to'));
            $batchSize = $this->batchSize();
            $limit = $this->limit();

            if ($sourceLocale === $targetLocale) {
                throw new RuntimeException(
                    'Source and target locales must be different.'
                );
            }

            if ($this->option('dry-run')) {
                return $this->dryRun(
                    $sourceLocale,
                    $targetLocale,
                    $limit
                );
            }

            $translator->assertConfigured();
            $lock = Cache::lock(
                "translate-generic-foods:{$sourceLocale}:{$targetLocale}",
                60 * 60
            );

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another generic food translation is already running.'
                );
            }

            try {
                $indexedExisting = $this->indexExistingTranslations(
                    $targetLocale
                );
                $stats = $this->translate(
                    $translator,
                    $sourceLocale,
                    $targetLocale,
                    $batchSize,
                    $limit
                );
            } finally {
                $lock->release();
            }

            $this->components->success(sprintf(
                'Translated %s generic foods from %s to %s; indexed %s existing %s names.',
                number_format($stats['translated']),
                $sourceLocale,
                $targetLocale,
                number_format($indexedExisting),
                $targetLocale
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{translated: int, characters: int}
     */
    private function translate(
        FoodNameTranslator $translator,
        string $sourceLocale,
        string $targetLocale,
        int $batchSize,
        ?int $limit
    ): array {
        $translated = 0;
        $characters = 0;
        $lastFoodId = 0;

        while ($limit === null || $translated < $limit) {
            $take = $limit === null
                ? $batchSize
                : min($batchSize, $limit - $translated);
            $foods = $this->eligibleQuery(
                $sourceLocale,
                $targetLocale
            )
                ->where('foods.id', '>', $lastFoodId)
                ->orderBy('foods.id')
                ->limit($take)
                ->get();

            if ($foods->isEmpty()) {
                break;
            }

            $lastFoodId = (int) $foods->last()->food_id;
            $names = $foods
                ->pluck('source_name')
                ->map(fn (string $name) => trim($name))
                ->all();
            $localizedNames = $translator->translate(
                $names,
                $sourceLocale,
                $targetLocale
            );
            $now = now();

            DB::transaction(function () use (
                $foods,
                $localizedNames,
                $targetLocale,
                $translator,
                $now
            ): void {
                foreach ($foods as $index => $food) {
                    $localizedName = $localizedNames[$index];

                    $inserted = DB::table(
                        'food_translations'
                    )->insertOrIgnore([
                        'food_id' => $food->food_id,
                        'locale' => $targetLocale,
                        'name' => $localizedName,
                        'translation_source' => $translator->source(),
                        'reviewed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    if ($inserted !== 1) {
                        continue;
                    }

                    DB::table('foods')
                        ->where('id', $food->food_id)
                        ->update([
                            'search_text' => $this->withLocalizedName(
                                $food->search_text,
                                $localizedName
                            ),
                            'updated_at' => $now,
                        ]);
                }
            });

            $translated += $foods->count();
            $characters += collect($names)->sum(
                fn (string $name) => mb_strlen($name)
            );
            $this->line(sprintf(
                'Translated %s · source characters %s · last food ID %s',
                number_format($translated),
                number_format($characters),
                number_format($lastFoodId)
            ));
        }

        return compact('translated', 'characters');
    }

    private function dryRun(
        string $sourceLocale,
        string $targetLocale,
        ?int $limit
    ): int {
        $query = $this->eligibleQuery($sourceLocale, $targetLocale);
        $eligible = (clone $query)->count();
        $selected = $limit === null ? $eligible : min($eligible, $limit);
        $charactersQuery = (clone $query)->orderBy('foods.id');

        if ($limit !== null) {
            $charactersQuery->limit($limit);
        }

        $characters = 0;

        foreach ($charactersQuery->cursor() as $food) {
            $characters += mb_strlen($food->source_name);
        }

        $this->table(
            ['From', 'To', 'Eligible', 'Selected', 'Source characters'],
            [[
                $sourceLocale,
                $targetLocale,
                number_format($eligible),
                number_format($selected),
                number_format($characters),
            ]]
        );
        $this->components->info(
            'Dry run only. No API request or database write was made.'
        );

        return self::SUCCESS;
    }

    private function eligibleQuery(
        string $sourceLocale,
        string $targetLocale
    ): Builder {
        return DB::table('foods')
            ->join(
                'food_translations as source_translation',
                function ($join) use ($sourceLocale): void {
                    $join
                        ->on(
                            'source_translation.food_id',
                            '=',
                            'foods.id'
                        )
                        ->where(
                            'source_translation.locale',
                            $sourceLocale
                        );
                }
            )
            ->leftJoin(
                'food_translations as target_translation',
                function ($join) use ($targetLocale): void {
                    $join
                        ->on(
                            'target_translation.food_id',
                            '=',
                            'foods.id'
                        )
                        ->where(
                            'target_translation.locale',
                            $targetLocale
                        );
                }
            )
            ->where('foods.food_type', 'generic')
            ->where('foods.is_active', true)
            ->whereNull('foods.canonical_food_id')
            ->whereNull('target_translation.id')
            ->whereNotNull('source_translation.name')
            ->where('source_translation.name', '!=', '')
            ->select([
                'foods.id as food_id',
                'foods.search_text',
                'source_translation.name as source_name',
            ]);
    }

    private function indexExistingTranslations(string $locale): int
    {
        $indexed = 0;
        $lastFoodId = 0;

        while (true) {
            $foods = DB::table('foods')
                ->join(
                    'food_translations',
                    'food_translations.food_id',
                    '=',
                    'foods.id'
                )
                ->where('foods.food_type', 'generic')
                ->where('foods.is_active', true)
                ->whereNull('foods.canonical_food_id')
                ->where('food_translations.locale', $locale)
                ->where('foods.id', '>', $lastFoodId)
                ->orderBy('foods.id')
                ->limit(500)
                ->get([
                    'foods.id as food_id',
                    'foods.search_text',
                    'food_translations.name as localized_name',
                ]);

            if ($foods->isEmpty()) {
                break;
            }

            $lastFoodId = (int) $foods->last()->food_id;
            $now = now();

            foreach ($foods as $food) {
                $searchText = $this->withLocalizedName(
                    $food->search_text,
                    $food->localized_name
                );

                if ($searchText === $food->search_text) {
                    continue;
                }

                DB::table('foods')
                    ->where('id', $food->food_id)
                    ->update([
                        'search_text' => $searchText,
                        'updated_at' => $now,
                    ]);
                $indexed++;
            }
        }

        return $indexed;
    }

    private function withLocalizedName(
        ?string $searchText,
        string $localizedName
    ): string {
        $searchText = trim((string) $searchText);
        $localizedName = trim($localizedName);

        if (
            $searchText !== ''
            && str_contains(
                mb_strtolower($searchText),
                mb_strtolower($localizedName)
            )
        ) {
            return $searchText;
        }

        return trim($searchText.' '.$localizedName);
    }

    private function locale(string $locale): string
    {
        $locale = mb_strtolower(trim($locale));

        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $locale) !== 1) {
            throw new RuntimeException(
                'Locales must use codes such as en, ro, or en-us.'
            );
        }

        return $locale;
    }

    private function batchSize(): int
    {
        $batchSize = filter_var(
            $this->option('batch'),
            FILTER_VALIDATE_INT
        );

        if ($batchSize === false || $batchSize < 1 || $batchSize > 2000) {
            throw new RuntimeException(
                '--batch must be an integer between 1 and 2,000.'
            );
        }

        return $batchSize;
    }

    private function limit(): ?int
    {
        $value = $this->option('limit');

        if ($value === null) {
            return null;
        }

        $limit = filter_var($value, FILTER_VALIDATE_INT);

        if ($limit === false || $limit < 1) {
            throw new RuntimeException(
                '--limit must be a positive integer.'
            );
        }

        return $limit;
    }
}
