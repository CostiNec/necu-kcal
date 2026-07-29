<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recipe extends Model
{
    protected $fillable = [
        'user_id',
        'food_id',
        'name',
        'cooked_weight',
        'total_calories',
        'total_protein',
        'total_carbohydrates',
        'total_fat',
        'total_fibre',
    ];

    protected function casts(): array
    {
        return [
            'cooked_weight' => 'float',
            'total_calories' => 'float',
            'total_protein' => 'float',
            'total_carbohydrates' => 'float',
            'total_fat' => 'float',
            'total_fibre' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(RecipeIngredient::class)->orderBy('position');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RecipeComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(RecipeReaction::class);
    }

    public function isVisibleTo(User $user): bool
    {
        return $this->user_id === $user->id
            || $user->isFriendsWith($this->user_id);
    }
}
