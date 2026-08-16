<?php

namespace App\Services;

use App\Models\Food;
use Illuminate\Support\Collection;

final readonly class FoodSearchPage
{
    /**
     * @param  Collection<int, Food>  $foods
     */
    public function __construct(
        public Collection $foods,
        public ?string $nextCursor,
    ) {}
}
