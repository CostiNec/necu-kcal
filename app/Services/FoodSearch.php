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
        $locale = app()->getLocale();

        return $query->where(function (Builder $builder) use (
            $locale,
            $search,
            $useSqliteWordFallback
        ) {
            $builder->whereHas(
                'translations',
                function (Builder $translationQuery) use (
                    $locale,
                    $search,
                    $useSqliteWordFallback
                ) {
                    $translationQuery
                        ->where('locale', $locale)
                        ->where(function (Builder $nameQuery) use (
                            $search,
                            $useSqliteWordFallback
                        ) {
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

    private function isBarcode(string $search): bool
    {
        return preg_match('/^\d{6,18}$/', $search) === 1;
    }
}
