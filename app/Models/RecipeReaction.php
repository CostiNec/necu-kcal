<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecipeReaction extends Model
{
    public const LIKE = 'like';

    public const DISLIKE = 'dislike';

    protected $fillable = [
        'recipe_id',
        'user_id',
        'reaction',
    ];

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
