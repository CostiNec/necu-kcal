<?php

namespace App\Support;

enum MassUnit: string
{
    case Grams = 'g';
    case Kilograms = 'kg';
    case Ounces = 'oz';
    case Pounds = 'lb';

    public function toGrams(float $amount): float
    {
        return $amount * match ($this) {
            self::Grams => 1,
            self::Kilograms => 1000,
            self::Ounces => 28.349523125,
            self::Pounds => 453.59237,
        };
    }
}
