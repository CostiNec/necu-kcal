<?php

namespace App\Services;

use App\Contracts\FoodSearchIndex;
use App\Models\Food;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TypesenseFoodSearch
{
    private const CURSOR_PREFIX = 'ts_';

    public function __construct(private Container $container) {}

    public function enabled(): bool
    {
        return config('food-search.driver') === 'typesense';
    }

    public function supports(string $search): bool
    {
        return $this->enabled()
            && trim($search) !== '';
    }

    public function isCursor(?string $cursor): bool
    {
        return is_string($cursor)
            && str_starts_with($cursor, self::CURSOR_PREFIX);
    }

    public function search(
        User $user,
        string $search,
        ?string $cursor = null,
        bool $favouritesOnly = false,
    ): FoodSearchPage {
        $perPage = (int) config('food-search.per_page', 20);
        $page = $this->pageFromCursor(
            $cursor,
            $search,
            $favouritesOnly,
            $perPage
        );
        $filter = $this->searchFilter($user, $favouritesOnly);

        if ($filter === null) {
            return new FoodSearchPage(collect(), null);
        }

        $isBarcode = preg_match('/^\d{6,18}$/', $search) === 1;
        $result = $this->container->make(FoodSearchIndex::class)->search(
            (string) config('scout.prefix')
                .config('food-search.typesense.collection', 'foods'),
            [
                'q' => $search,
                'query_by' => $isBarcode
                    ? 'barcode'
                    : 'name,translation_names,alias_names,brand',
                'query_by_weights' => $isBarcode ? '1' : '10,10,7,3',
                'num_typos' => $isBarcode
                    ? '0'
                    : (string) config(
                        'food-search.typesense.num_typos',
                        '2,2,1,1'
                    ),
                'prefix' => ! $isBarcode,
                'filter_by' => $filter,
                'sort_by' => implode(',', [
                    'search_priority:asc',
                    '_text_match:desc',
                    'ranking_score:desc',
                ]),
                'drop_tokens_threshold' => 0,
                'max_candidates' => (int) config(
                    'food-search.typesense.max_candidates',
                    50
                ),
                'prioritize_exact_match' => true,
                'exhaustive_search' => false,
                'page' => $page,
                'per_page' => $perPage,
            ]
        );

        $ids = collect($result['hits'] ?? [])
            ->pluck('document.id')
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();
        $foods = $this->hydrate($user, $ids);
        $found = (int) ($result['found'] ?? 0);
        $nextCursor = $page * $perPage < min(
            $found,
            (int) config('food-search.typesense.max_results', 1000)
        )
            ? $this->cursorForPage(
                $page + 1,
                $search,
                $favouritesOnly
            )
            : null;

        return new FoodSearchPage($foods, $nextCursor);
    }

    private function searchFilter(
        User $user,
        bool $favouritesOnly
    ): ?string {
        $visibility = $this->visibilityFilter($user);

        if (! $favouritesOnly) {
            return $visibility;
        }

        $favouriteIds = DB::table('food_favourites')
            ->where('user_id', $user->id)
            ->pluck('food_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values();

        if ($favouriteIds->isEmpty()) {
            return null;
        }

        return sprintf(
            '%s && id:=[%s]',
            $visibility,
            $favouriteIds->implode(',')
        );
    }

    private function visibilityFilter(User $user): string
    {
        $friendIds = Friendship::query()
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->where(
                fn ($query) => $query
                    ->where('user_id', $user->id)
                    ->orWhere('friend_id', $user->id)
            )
            ->get(['user_id', 'friend_id'])
            ->map(
                fn (Friendship $friendship) => $friendship->user_id === $user->id
                    ? $friendship->friend_id
                    : $friendship->user_id
            )
            ->unique()
            ->values();
        $visibility = [
            'is_public:=true',
            "user_id:={$user->id}",
        ];

        if ($friendIds->isNotEmpty()) {
            $visibility[] = sprintf(
                '(food_type:=recipe && user_id:=[%s])',
                $friendIds->implode(',')
            );
        }

        return '('.implode(' || ', $visibility).')';
    }

    /**
     * @param  Collection<int, int>  $ids
     * @return Collection<int, Food>
     */
    private function hydrate(User $user, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $positions = $ids
            ->mapWithKeys(fn (int $id, int $position) => [$id => $position]);

        return Food::query()
            ->visibleTo($user)
            ->whereIntegerInRaw('foods.id', $ids->all())
            ->leftJoin(
                'food_favourites as favourite',
                function ($join) use ($user): void {
                    $join
                        ->on('favourite.food_id', '=', 'foods.id')
                        ->where('favourite.user_id', '=', $user->id);
                }
            )
            ->select('foods.*')
            ->selectRaw(
                'CASE WHEN favourite.id IS NULL THEN 0 ELSE 1 END AS is_favourite'
            )
            ->with([
                'translation',
                'translations',
                'recipe:id,food_id',
                'creator:id,name,username',
            ])
            ->get()
            ->sortBy(fn (Food $food) => $positions->get($food->id))
            ->values();
    }

    private function pageFromCursor(
        ?string $cursor,
        string $search,
        bool $favouritesOnly,
        int $perPage,
    ): int {
        if (! $this->isCursor($cursor)) {
            return 1;
        }

        $encoded = substr((string) $cursor, strlen(self::CURSOR_PREFIX));
        $padding = (4 - strlen($encoded) % 4) % 4;
        $decoded = base64_decode(
            strtr($encoded.str_repeat('=', $padding), '-_', '+/'),
            true
        );
        $payload = is_string($decoded)
            ? json_decode($decoded, true)
            : null;
        $page = is_array($payload) ? (int) ($payload['page'] ?? 0) : 0;
        $maxPage = max(1, intdiv(
            (int) config('food-search.typesense.max_results', 1000),
            $perPage
        ));

        if (
            ! is_array($payload)
            || ! hash_equals(
                (string) ($payload['query'] ?? ''),
                $this->cursorQuery($search, $favouritesOnly)
            )
            || $page < 1
            || $page > $maxPage
        ) {
            return 1;
        }

        return $page;
    }

    private function cursorForPage(
        int $page,
        string $search,
        bool $favouritesOnly
    ): string {
        $payload = base64_encode((string) json_encode([
            'page' => $page,
            'query' => $this->cursorQuery($search, $favouritesOnly),
        ], JSON_THROW_ON_ERROR));

        return self::CURSOR_PREFIX.rtrim(
            strtr($payload, '+/', '-_'),
            '='
        );
    }

    private function cursorQuery(
        string $search,
        bool $favouritesOnly
    ): string {
        return hash('sha256', $search.'|'.(int) $favouritesOnly);
    }
}
