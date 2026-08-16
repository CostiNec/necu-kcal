<?php

namespace App\Services;

use App\Contracts\FoodSearchIndex;
use Typesense\Client;

class TypesenseFoodSearchIndex implements FoodSearchIndex
{
    public function __construct(private Client $client) {}

    public function search(string $collection, array $parameters): array
    {
        return $this->client
            ->getCollections()
            ->{$collection}
            ->getDocuments()
            ->search($parameters);
    }
}
