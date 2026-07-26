<?php

namespace App\Models;

use App\Support\HtmlText;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FoodAlias extends Model
{
    protected $fillable = [
        'food_id',
        'locale',
        'name',
        'alias_type',
        'priority',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => HtmlText::decode($value),
            set: fn (string $value) => HtmlText::decode($value),
        );
    }

    public function food(): BelongsTo
    {
        return $this->belongsTo(Food::class);
    }
}
