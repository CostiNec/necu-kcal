<?php

namespace App\Models;

use App\Support\HtmlText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Food extends Model
{
    protected $table = 'foods';

    protected $attributes = [
        'food_type' => 'generic',
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
            'search_priority' => 'integer',
            'is_common' => 'boolean',
            'common_priority' => 'integer',
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

    /**
     * Shape shared with a future Laravel Scout/Typesense index.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        $this->loadMissing(['translations', 'aliases']);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'brand' => $this->brand,
            'barcode' => $this->barcode,
            'food_type' => $this->food_type,
            'is_common' => $this->is_common,
            'common_priority' => $this->common_priority ?? 65535,
            'search_priority' => $this->search_priority,
            'popularity_score' => $this->popularity_score,
            'translations' => $this->translations
                ->mapWithKeys(fn (FoodTranslation $translation) => [
                    $translation->locale => $translation->name,
                ])
                ->all(),
            'aliases' => $this->aliases
                ->groupBy('locale')
                ->map->pluck('name')
                ->all(),
        ];
    }
}
