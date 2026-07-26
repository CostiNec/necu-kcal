<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NutritionTarget extends Model
{
    protected $fillable = [
        'user_id',
        'calories',
        'protein',
        'carbohydrates',
        'fat',
    ];

    protected function casts(): array
    {
        return [
            'calories' => 'integer',
            'protein' => 'integer',
            'carbohydrates' => 'integer',
            'fat' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
