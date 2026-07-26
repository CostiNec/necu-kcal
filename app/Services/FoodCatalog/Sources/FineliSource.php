<?php

namespace App\Services\FoodCatalog\Sources;

use App\Services\FoodCatalog\AbstractCatalogSource;
use App\Services\FoodCatalog\ZipCsvReader;
use Generator;

class FineliSource extends AbstractCatalogSource
{
    public function __construct(
        private readonly ZipCsvReader $csv
    ) {}

    public function key(): string
    {
        return 'fineli';
    }

    public function sourceCode(): string
    {
        return 'fineli';
    }

    public function files(): array
    {
        $settings = config('generic-food-sources.sources.fineli');

        return ['archive' => [
            'url' => $settings['url'],
            'path' => $settings['path'],
        ]];
    }

    public function records(array $paths): Generator
    {
        $path = $paths['archive'];
        $foods = [];

        foreach (
            $this->csv->rows($path, 'foodname_EN.csv', ';') as $row
        ) {
            $id = $this->text($row['FOODID'] ?? null, 64);
            $name = $this->text($row['FOODNAME'] ?? null);

            if ($id !== null && $name !== null) {
                $foods[$id] = [
                    'name' => $this->sentenceCase($name),
                    'nutrients' => [],
                ];
            }
        }

        $wanted = [
            'PROT' => 'protein',
            'FAT' => 'fat',
            'CHOAVL' => 'carbohydrates',
            'CHOCDF' => 'carbohydrates_fallback',
            'FIBC' => 'fibre',
            'SUGAR' => 'sugar',
            'NA' => 'sodium_mg',
            'ENERC' => 'energy_kj',
        ];

        foreach (
            $this->csv->rows($path, 'component_value.csv', ';') as $row
        ) {
            $id = $this->text($row['FOODID'] ?? null, 64);
            $component = $this->text($row['EUFDNAME'] ?? null, 32);

            if (
                $id === null
                || $component === null
                || ! isset($foods[$id], $wanted[$component])
            ) {
                continue;
            }

            $foods[$id]['nutrients'][$wanted[$component]] = $this->number(
                $row['BESTLOC'] ?? null
            );
        }

        foreach ($foods as $id => $food) {
            $nutrients = $food['nutrients'];
            $nutrients['calories'] = isset($nutrients['energy_kj'])
                ? round($nutrients['energy_kj'] / 4.184, 3)
                : null;
            $nutrients['carbohydrates'] ??=
                $nutrients['carbohydrates_fallback'] ?? null;

            $record = $this->record(
                externalId: $id,
                name: $food['name'],
                nutrients: $nutrients,
                common: true,
                commonPriority: min(650, 350 + mb_strlen($food['name']))
            );

            if ($record !== null) {
                yield $record;
            }
        }
    }

    private function sentenceCase(string $name): string
    {
        return mb_strtoupper(mb_substr($name, 0, 1))
            .mb_strtolower(mb_substr($name, 1));
    }
}
