<?php

namespace App\Services\FoodCatalog\Sources;

use App\Services\FoodCatalog\AbstractCatalogSource;
use Generator;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CofidSource extends AbstractCatalogSource
{
    public function key(): string
    {
        return 'cofid';
    }

    public function sourceCode(): string
    {
        return 'cofid';
    }

    public function files(): array
    {
        $settings = config('generic-food-sources.sources.cofid');

        return ['workbook' => [
            'url' => $settings['url'],
            'path' => $settings['path'],
        ]];
    }

    public function records(array $paths): Generator
    {
        $this->ensureMemory();
        $path = $paths['workbook'];
        $sodium = $this->sodiumByFood($path);
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['1.3 Proximates']);
        $sheet = $reader->load($path)->getActiveSheet();

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $values = $sheet->rangeToArray(
                "A{$row}:AB{$row}",
                null,
                true,
                true,
                false
            )[0];
            $id = $this->text($values[0] ?? null, 64);
            $name = $this->text($values[1] ?? null);

            if ($id === null || $name === null) {
                continue;
            }

            $group = $this->text($values[3] ?? null, 16);
            $nutrients = [
                'protein' => $this->number($values[9] ?? null),
                'fat' => $this->number($values[10] ?? null),
                'carbohydrates' => $this->number($values[11] ?? null),
                'calories' => $this->number($values[12] ?? null, 10000),
                'sugar' => $this->number($values[16] ?? null),
                'fibre' => $this->number(
                    $values[25] ?? $values[24] ?? null
                ),
                'saturated_fat' => $this->number($values[27] ?? null),
                'sodium_mg' => $sodium[$id] ?? null,
            ];
            $basis = $this->isAlcoholicDrink($name, $group) ? 'ml' : 'g';
            $record = $this->record(
                externalId: $id,
                name: $name,
                nutrients: $nutrients,
                basisUnit: $basis,
                extraSearchText: $this->text($values[2] ?? null, 1000)
            );

            if ($record !== null) {
                yield $record;
            }
        }
    }

    private function ensureMemory(): void
    {
        $limit = ini_get('memory_limit');

        if ($limit !== '-1' && $this->memoryBytes($limit) < 768 * 1024 * 1024) {
            ini_set('memory_limit', '768M');
        }
    }

    private function memoryBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    /**
     * @return array<string, float>
     */
    private function sodiumByFood(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['1.4 Inorganics']);
        $sheet = $reader->load($path)->getActiveSheet();
        $sodium = [];

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $values = $sheet->rangeToArray(
                "A{$row}:H{$row}",
                null,
                true,
                true,
                false
            )[0];
            $id = $this->text($values[0] ?? null, 64);
            $value = $this->number($values[7] ?? null);

            if ($id !== null && $value !== null) {
                $sodium[$id] = $value;
            }
        }

        return $sodium;
    }

    private function isAlcoholicDrink(string $name, ?string $group): bool
    {
        if ($group === null || ! str_starts_with($group, 'Q')) {
            return false;
        }

        return preg_match(
            '/\b(beer|wine|cider|spirit|liqueur|brandy|gin|rum|vodka|whisk|sherry|port)\b/i',
            $name
        ) === 1;
    }
}
