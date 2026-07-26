<?php

namespace App\Models;

use App\Support\HtmlText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    public function translation(): HasOne
    {
        return $this->hasOne(FoodTranslation::class)
            ->where('locale', app()->getLocale());
    }

    public function localizedName(): string
    {
        return $this->translation?->name ?? $this->name;
    }

    public function recipe(): HasOne
    {
        return $this->hasOne(Recipe::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(
            fn (Builder $builder) => $builder
                ->where('foods.is_public', true)
                ->orWhere('foods.user_id', $user->id)
        );
    }
}
