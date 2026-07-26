<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeIngredient extends Model
{
    protected $fillable = [
        'recipe_id',
        'food_id',
        'food_name',
        'amount',
        'unit',
        'calories',
        'protein',
        'carbohydrates',
        'fat',
        'fibre',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'calories' => 'float',
            'protein' => 'float',
            'carbohydrates' => 'float',
            'fat' => 'float',
            'fibre' => 'float',
            'position' => 'integer',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
