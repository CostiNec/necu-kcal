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
        'food_translation_key',
        'brand',
        'unit_type',
        'serving_name',
        'serving_translation_key',
        'quantity',
        'amount',
        'calories',
        'protein',
        'carbohydrates',
        'fat',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'amount' => 'float',
            'calories' => 'float',
            'protein' => 'float',
            'carbohydrates' => 'float',
            'fat' => 'float',
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
