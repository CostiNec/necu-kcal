<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiaryEntry extends Model
{
    protected $fillable = [
        'diary_day_id',
        'food_id',
        'meal',
        'food_name',
        'brand',
        'unit',
        'quantity',
        'amount',
        'total_grams',
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
            'quantity' => 'float',
            'amount' => 'float',
            'total_grams' => 'float',
            'calories' => 'float',
            'protein' => 'float',
            'carbohydrates' => 'float',
            'fat' => 'float',
            'fibre' => 'float',
            'position' => 'integer',
        ];
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(DiaryDay::class, 'diary_day_id');
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
