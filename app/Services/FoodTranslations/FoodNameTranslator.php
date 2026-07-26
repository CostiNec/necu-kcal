<?php

namespace App\Services\FoodTranslations;

interface FoodNameTranslator
{
    public function assertConfigured(): void;

    /**
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    public function translate(
        array $names,
        string $sourceLocale,
        string $targetLocale
    ): array;

    public function source(): string;
}
