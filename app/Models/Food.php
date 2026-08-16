<?php

namespace App\Models;

use App\Support\HtmlText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\Searchable;

class Food extends Model
{
    use Searchable;

    protected $table = 'foods';

    protected $attributes = [
        'food_type' => 'generic',
        'is_active' => true,
    ];

    protected $fillable = [
        'user_id',
        'food_type',
        'search_priority',
        'is_common',
        'common_priority',
        'canonical_food_id',
        'nutrition_source_food_id',
        'name',
        'brand',
        'barcode',
        'calories',
        'nutrition_basis_amount',
        'nutrition_basis_unit',
        'protein',
        'carbohydrates',
        'fat',
        'fibre',
        'sugar',
        'sodium',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'float',
            'nutrition_basis_amount' => 'float',
            'protein' => 'float',
            'carbohydrates' => 'float',
            'fat' => 'float',
            'fibre' => 'float',
            'sugar' => 'float',
            'sodium' => 'float',
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'search_priority' => 'integer',
            'is_common' => 'boolean',
            'common_priority' => 'integer',
            'popularity_score' => 'integer',
            'canonical_food_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Food $food): void {
            if (
                (! $food->exists || $food->isDirty('food_type'))
                && ! $food->isDirty('search_priority')
            ) {
                $food->search_priority = match ($food->food_type) {
                    'generic' => 0,
                    'custom', 'recipe' => 1,
                    default => 2,
                };
            }

            if (
                (! $food->exists
                    || $food->isDirty(['name', 'brand', 'barcode']))
                && ! $food->isDirty('search_text')
            ) {
                $food->search_text = collect([
                    $food->name,
                    $food->brand,
                    $food->barcode,
                ])->filter()->unique()->implode(' ');
            }
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => HtmlText::decode($value),
            set: fn (string $value) => HtmlText::decode($value),
        );
    }

    protected function brand(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => HtmlText::decode($value),
            set: fn (?string $value) => HtmlText::decode($value),
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(FoodTranslation::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(FoodAlias::class);
    }

    public function canonicalFood(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_food_id');
    }

    public function duplicateFoods(): HasMany
    {
        return $this->hasMany(self::class, 'canonical_food_id');
    }

    public function nutritionSourceFood(): BelongsTo
    {
        return $this->belongsTo(self::class, 'nutrition_source_food_id');
    }

    public function translation(): HasOne
    {
        return $this->hasOne(FoodTranslation::class)
            ->where('locale', app()->getLocale());
    }

    public function localizedName(): string
    {
        return $this->translation?->name ?? $this->name;
    }

    public function localizedNameMatching(string $search): string
    {
        $search = mb_strtolower(trim($search));

        if ($search === '') {
            return $this->localizedName();
        }

        $matchingTranslation = $this->translations
            ->map(fn (FoodTranslation $translation) => [
                'translation' => $translation,
                'match_priority' => $this->nameMatchPriority(
                    $translation->name,
                    $search
                ),
            ])
            ->filter(fn (array $match) => $match['match_priority'] !== null)
            ->sortBy(fn (array $match) => [
                $match['match_priority'],
                $match['translation']->locale === app()->getLocale() ? 0 : 1,
            ])
            ->first();

        return $matchingTranslation['translation']->name
            ?? $this->localizedName();
    }

    private function nameMatchPriority(string $name, string $search): ?int
    {
        $name = mb_strtolower($name);

        if ($name === $search) {
            return 0;
        }

        if (str_starts_with($name, $search)) {
            return 1;
        }

        return preg_match(
            '/[^\p{L}\p{N}]'.preg_quote($search, '/').'/u',
            $name
        ) === 1 ? 2 : null;
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query
            ->whereNull('foods.canonical_food_id')
            ->where('foods.is_active', true)
            ->where(
                fn (Builder $builder) => $builder
                    ->where('foods.is_public', true)
                    ->orWhere('foods.user_id', $user->id)
                    ->orWhere(function (Builder $friendRecipe) use ($user) {
                        $friendRecipe
                            ->where('foods.food_type', 'recipe')
                            ->whereExists(function ($query) use ($user) {
                                $query
                                    ->select(DB::raw(1))
                                    ->from('friendships')
                                    ->where('friendships.status', Friendship::STATUS_ACCEPTED)
                                    ->where(function ($friendship) use ($user) {
                                        $friendship
                                            ->where(function ($pair) use ($user) {
                                                $pair
                                                    ->where('friendships.user_id', $user->id)
                                                    ->whereColumn(
                                                        'friendships.friend_id',
                                                        'foods.user_id'
                                                    );
                                            })
                                            ->orWhere(function ($pair) use ($user) {
                                                $pair
                                                    ->where('friendships.friend_id', $user->id)
                                                    ->whereColumn(
                                                        'friendships.user_id',
                                                        'foods.user_id'
                                                    );
                                            });
                                    });
                            });
                    })
            );
    }

    public function searchableAs(): string
    {
        return config('scout.prefix')
            .config('food-search.typesense.collection', 'foods');
    }

    public function shouldBeSearchable(): bool
    {
        return $this->is_active && $this->canonical_food_id === null;
    }

    public function searchIndexShouldBeUpdated(): bool
    {
        return config('scout.driver') === 'typesense';
    }

    /**
     * @param  EloquentCollection<int, Food>  $models
     * @return EloquentCollection<int, Food>
     */
    public function makeSearchableUsing(
        EloquentCollection $models
    ): EloquentCollection {
        return $models->loadMissing(['translations', 'aliases']);
    }

    protected function makeAllSearchableUsing(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereNull('canonical_food_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['translations', 'aliases']);

        return array_filter([
            'id' => (string) $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'translation_names' => $this->translations
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'alias_names' => $this->aliases
                ->pluck('name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'user_id' => $this->user_id === null
                ? null
                : (int) $this->user_id,
            'is_public' => (bool) $this->is_public,
            'food_type' => $this->food_type,
            'search_priority' => (int) $this->search_priority,
            'ranking_score' => $this->typesenseRankingScore(),
        ], fn ($value) => $value !== null);
    }

    private function typesenseRankingScore(): int
    {
        $popularity = min(
            max((int) $this->popularity_score, 0),
            999_999
        );

        if (! $this->is_common) {
            return $popularity;
        }

        $commonPriority = min(
            max((int) ($this->common_priority ?? 65535), 0),
            65535
        );

        return 2_000_000_000_000
            + ((65535 - $commonPriority) * 1_000_000)
            + $popularity;
    }
}
