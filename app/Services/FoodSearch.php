<?php

namespace App\Services;

use App\Models\Food;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FoodSearch
{
    public function query(User $user, string $search = ''): Builder
    {
        $search = trim($search);
        $query = Food::query()->visibleTo($user);

        if ($search === '') {
            return $query;
        }

        if ($this->isBarcode($search)) {
            return $query->where('foods.barcode', $search);
        }

        $booleanSearch = $this->booleanSearch($search);
        $useMySqlFullText = DB::getDriverName() === 'mysql'
            && $booleanSearch !== '';

        if ($useMySqlFullText) {
            return $query->whereRaw(
                'MATCH(foods.search_text) AGAINST (? IN BOOLEAN MODE)',
                [$booleanSearch]
            );
        }

        $useSqliteWordFallback = DB::getDriverName() === 'sqlite';

        return $query->where(function (Builder $builder) use (
            $search,
            $useSqliteWordFallback
        ) {
            $builder->whereHas(
                'translations',
                function (Builder $translationQuery) use (
                    $search,
                    $useSqliteWordFallback
                ) {
                    $translationQuery->where(function (
                        Builder $nameQuery
                    ) use ($search, $useSqliteWordFallback) {
                        $nameQuery->where(
                            'food_translations.name',
                            'like',
                            "{$search}%"
                        );

                        if ($useSqliteWordFallback) {
                            $nameQuery->orWhereRaw(
                                'instr(lower(food_translations.name), lower(?)) > 0',
                                [" {$search}"]
                            );
                        }
                    });
                }
            );

            $builder->orWhereHas(
                'aliases',
                function (Builder $aliasQuery) use (
                    $search,
                    $useSqliteWordFallback
                ) {
                    $aliasQuery->where(function (
                        Builder $nameQuery
                    ) use ($search, $useSqliteWordFallback) {
                        $nameQuery->where(
                            'food_aliases.name',
                            'like',
                            "{$search}%"
                        );

                        if ($useSqliteWordFallback) {
                            $nameQuery->orWhereRaw(
                                'instr(lower(food_aliases.name), lower(?)) > 0',
                                [" {$search}"]
                            );
                        }
                    });
                }
            );

            $builder->orWhere(function (Builder $foodQuery) use (
                $search,
                $useSqliteWordFallback
            ) {
                $foodQuery
                    ->where('foods.name', 'like', "{$search}%")
                    ->orWhere('foods.brand', 'like', "{$search}%");

                if ($useSqliteWordFallback) {
                    $foodQuery
                        ->orWhereRaw(
                            'instr(lower(foods.name), lower(?)) > 0',
                            [" {$search}"]
                        )
                        ->orWhereRaw(
                            'instr(lower(foods.brand), lower(?)) > 0',
                            [" {$search}"]
                        );
                }
            });
        });
    }

    public function order(Builder $query, string $search = ''): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query
                ->orderBy('foods.name')
                ->orderBy('foods.id');
        }

        if ($this->isBarcode($search)) {
            return $query->orderBy('foods.id');
        }

        $locale = app()->getLocale();
        $query->leftJoin(
            'food_translations as ranked_translation',
            function ($join) use ($locale): void {
                $join
                    ->on(
                        'ranked_translation.food_id',
                        '=',
                        'foods.id'
                    )
                    ->where('ranked_translation.locale', $locale);
            }
        );

        $localizedName = 'COALESCE(ranked_translation.name, foods.name)';
        $normalizedSearch = mb_strtolower($search);
        $exact = $this->quote($normalizedSearch);
        $prefix = $this->quote(
            $this->escapeLike($normalizedSearch).'%'
        );
        $localizedLower = "LOWER({$localizedName})";
        $baseLower = 'LOWER(foods.name)';
        $translatedExact = implode(' ', [
            'EXISTS (SELECT 1 FROM food_translations AS exact_translation',
            'WHERE exact_translation.food_id = foods.id',
            "AND LOWER(exact_translation.name) = {$exact})",
        ]);
        $translatedPrefix = implode(' ', [
            'EXISTS (SELECT 1 FROM food_translations AS prefix_translation',
            'WHERE prefix_translation.food_id = foods.id',
            "AND LOWER(prefix_translation.name) LIKE {$prefix} ESCAPE '=')",
        ]);
        $aliasExact = implode(' ', [
            'EXISTS (SELECT 1 FROM food_aliases AS exact_alias',
            'WHERE exact_alias.food_id = foods.id',
            "AND LOWER(exact_alias.name) = {$exact})",
        ]);
        $aliasPrefix = implode(' ', [
            'EXISTS (SELECT 1 FROM food_aliases AS prefix_alias',
            'WHERE prefix_alias.food_id = foods.id',
            "AND LOWER(prefix_alias.name) LIKE {$prefix} ESCAPE '=')",
        ]);
        $exactMatch = implode(' OR ', [
            "{$localizedLower} = {$exact}",
            "{$baseLower} = {$exact}",
            $translatedExact,
            $aliasExact,
        ]);
        $prefixMatch = implode(' OR ', [
            "{$localizedLower} LIKE {$prefix} ESCAPE '='",
            "{$baseLower} LIKE {$prefix} ESCAPE '='",
            $translatedPrefix,
            $aliasPrefix,
        ]);
        $lengthFunction = DB::getDriverName() === 'sqlite'
            ? 'LENGTH'
            : 'CHAR_LENGTH';
        $matchPriority = implode(' ', [
            'CASE',
            "WHEN ({$exactMatch}) THEN 0",
            "WHEN ({$prefixMatch}) THEN 1",
            'ELSE 2',
            'END',
        ]);

        return $query
            ->selectRaw(
                "{$matchPriority} AS search_match_priority"
            )
            ->selectRaw(
                "{$lengthFunction}({$localizedName}) AS search_name_length"
            )
            ->selectRaw(
                "{$localizedLower} AS search_sort_name"
            )
            ->selectRaw(
                'COALESCE(foods.common_priority, 65535) AS search_common_priority'
            )
            ->orderBy('foods.search_priority')
            ->orderBy('search_match_priority')
            ->orderByDesc('foods.is_common')
            ->orderBy('search_common_priority')
            ->orderBy('search_name_length')
            ->orderByDesc('foods.popularity_score')
            ->orderBy('search_sort_name')
            ->orderBy('foods.id');
    }

    private function booleanSearch(string $search): string
    {
        preg_match_all('/[\p{L}\p{N}]+/u', $search, $matches);

        return collect($matches[0] ?? [])
            ->filter(fn (string $term) => mb_strlen($term) >= 3)
            ->take(8)
            ->map(fn (string $term) => "+{$term}*")
            ->implode(' ');
    }

    private function isBarcode(string $search): bool
    {
        return preg_match('/^\d{6,18}$/', $search) === 1;
    }

    private function escapeLike(string $value): string
    {
        return str_replace(
            ['=', '%', '_'],
            ['==', '=%', '=_'],
            $value
        );
    }

    private function quote(string $value): string
    {
        $quoted = DB::connection()->getPdo()->quote($value);

        if ($quoted === false) {
            throw new RuntimeException(
                'Unable to prepare the food search ranking.'
            );
        }

        return $quoted;
    }
}
