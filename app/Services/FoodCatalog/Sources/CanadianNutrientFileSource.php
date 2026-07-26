<?php

namespace App\Services\FoodCatalog\Sources;

use App\Services\FoodCatalog\AbstractCatalogSource;
use App\Services\FoodCatalog\ZipCsvReader;
use Generator;

class CanadianNutrientFileSource extends AbstractCatalogSource
{
    public function __construct(
        private readonly ZipCsvReader $csv
    ) {}

    public function key(): string
    {
        return 'cnf';
    }

    public function sourceCode(): string
    {
        return 'canadian_nutrient_file';
    }

    public function files(): array
    {
        $settings = config('generic-food-sources.sources.cnf');

        return ['archive' => [
            'url' => $settings['url'],
            'path' => $settings['path'],
        ]];
    }

    public function records(array $paths): Generator
    {
        $path = $paths['archive'];
        $foods = [];

        foreach ($this->csv->rows($path, 'Food_Name.csv') as $row) {
            $id = $this->text($row['Food_Code'] ?? null, 64);
            $name = $this->text($row['Food_Description_EN'] ?? null);

            if ($id === null || $name === null) {
                continue;
            }

            $foods[$id] = [
                'name' => $name,
                'alternate' => $this->text(
                    $row['Alternate_Description_EN'] ?? null
                ),
                'scientific' => $this->text(
                    $row['ScientificName'] ?? null
                ),
                'nutrients' => [],
            ];
        }

        $wanted = [
            '203' => 'protein',
            '204' => 'fat',
            '205' => 'carbohydrates',
            '208' => 'calories',
            '269' => 'sugar',
            '291' => 'fibre',
            '307' => 'sodium_mg',
        ];

        foreach ($this->csv->rows($path, 'Nutrient_Amount.csv') as $row) {
            $id = $this->text($row['Food_Code'] ?? null, 64);
            $code = $this->text($row['Nutrient_Code'] ?? null, 16);

            if (
                $id === null
                || $code === null
                || ! isset($foods[$id], $wanted[$code])
            ) {
                continue;
            }

            $foods[$id]['nutrients'][$wanted[$code]] = $this->number(
                $row['Nutrient_Amount'] ?? null
            );
        }

        foreach ($foods as $id => $food) {
            $aliases = [];

            if ($food['alternate'] !== null) {
                $aliases[] = [
                    'locale' => 'en',
                    'name' => $food['alternate'],
                    'alias_type' => 'source_synonym',
                    'priority' => 120,
                ];
            }

            $record = $this->record(
                externalId: $id,
                name: $food['name'],
                nutrients: $food['nutrients'],
                aliases: $aliases,
                extraSearchText: $food['scientific']
            );

            if ($record !== null) {
                yield $record;
            }
        }
    }
}
