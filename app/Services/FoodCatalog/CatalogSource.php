<?php

namespace App\Services\FoodCatalog;

use Generator;

interface CatalogSource
{
    public function key(): string;

    public function sourceCode(): string;

    /**
     * @return array<string, array{url: string, path: string}>
     */
    public function files(): array;

    /**
     * @param  array<string, string>  $paths
     * @return Generator<int, array<string, mixed>>
     */
    public function records(array $paths): Generator;
}
