<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodTranslation extends Model
{
    protected $fillable = [
        'food_id',
        'locale',
        'name',
    ];

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
