<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodServing extends Model
{
    protected $fillable = [
        'food_id',
        'name',
        'translation_key',
        'amount',
        'is_default',
    ];

    protected function casts(): array
    {
        return ['amount' => 'float', 'is_default' => 'boolean'];
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
