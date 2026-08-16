<?php

namespace App\Contracts;

interface FoodSearchIndex
{
    /**
     * @param  array<string, bool|float|int|string>  $parameters
     * @return array<string, mixed>
     */
    public function search(string $collection, array $parameters): array;
}
