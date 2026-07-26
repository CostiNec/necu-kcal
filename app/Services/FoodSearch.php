<?php

namespace App\Services;

use App\Models\Food;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FoodSearch
{
    public function query(User $user, string $search = ''): Builder
    {
        $search = trim($search);
        $query = Food::query()->visibleTo($user);

        if ($search === '') {
            return $query;
        }

        $booleanSearch = $this->booleanSearch($search);
        $useFullText = DB::getDriverName() === 'mysql'
            && $booleanSearch !== '';
        $useSqliteWordFallback = DB::getDriverName() === 'sqlite';
        $locale = app()->getLocale();

        return $query->where(function (Builder $builder) use (
            $booleanSearch,
            $locale,
            $search,
            $useSqliteWordFallback,
            $useFullText
        ) {
            $builder->where('foods.barcode', $search);

            $builder->orWhereHas(
                'translations',
                function (Builder $translationQuery) use (
                    $booleanSearch,
                    $locale,
                    $search,
                    $useSqliteWordFallback,
                    $useFullText
                ) {
                    $translationQuery
                        ->where('locale', $locale)
                        ->where(function (Builder $nameQuery) use (
                            $booleanSearch,
                            $search,
                            $useSqliteWordFallback,
                            $useFullText
                        ) {
                            if ($useFullText) {
                                $nameQuery->whereRaw(
                                    'MATCH(food_translations.name) AGAINST (? IN BOOLEAN MODE)',
                                    [$booleanSearch]
                                );

                                return;
                            }

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

            if ($useFullText) {
                $builder->orWhereRaw(
                    'MATCH(foods.name, foods.brand) AGAINST (? IN BOOLEAN MODE)',
                    [$booleanSearch]
                );

                return;
            }

            $builder
                ->orWhere('foods.name', 'like', "{$search}%")
                ->orWhere('foods.brand', 'like', "{$search}%");
        });
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
}
