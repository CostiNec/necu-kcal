<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Food extends Model
{
    protected $table = 'foods';

    protected $fillable = [
        'user_id',
        'name',
        'brand',
        'barcode',
        'calories',
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
            'protein' => 'float',
            'carbohydrates' => 'float',
            'fat' => 'float',
            'fibre' => 'float',
            'sugar' => 'float',
            'sodium' => 'float',
            'is_public' => 'boolean',
        ];
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
