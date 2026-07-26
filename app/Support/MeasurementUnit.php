<?php

namespace App\Support;

enum MeasurementUnit: string
{
    case Grams = 'g';
    case Kilograms = 'kg';
    case Ounces = 'oz';
    case Pounds = 'lb';
    case Millilitres = 'ml';
    case Litres = 'l';

    public function basisUnit(): string
    {
        return match ($this) {
            self::Grams,
            self::Kilograms,
            self::Ounces,
            self::Pounds => 'g',
            self::Millilitres,
            self::Litres => 'ml',
        };
    }

    public function toBaseAmount(float $amount): float
    {
        return $amount * match ($this) {
            self::Grams, self::Millilitres => 1,
            self::Kilograms, self::Litres => 1000,
            self::Ounces => 28.349523125,
            self::Pounds => 453.59237,
        };
    }

    public function isCompatibleWith(string $basisUnit): bool
    {
        return $this->basisUnit() === $basisUnit;
    }
}
