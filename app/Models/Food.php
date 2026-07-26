<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Food extends Model
{
    protected $table = 'foods';

    protected $fillable = [
        'user_id',
        'name',
        'translation_key',
        'brand',
        'barcode',
        'calories',
        'protein',
        'carbohydrates',
        'fat',
        'fibre',
        'sugar',
        'sodium',
        'unit_type',
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

    public function servings(): HasMany
    {
        return $this->hasMany(FoodServing::class);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(
            fn (Builder $builder) => $builder
                ->where('is_public', true)
                ->orWhere('user_id', $user->id)
        );
    }
}
