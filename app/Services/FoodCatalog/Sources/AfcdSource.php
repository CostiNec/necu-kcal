<?php

namespace App\Services\FoodCatalog\Sources;

use App\Services\FoodCatalog\AbstractCatalogSource;
use Generator;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AfcdSource extends AbstractCatalogSource
{
    public function key(): string
    {
        return 'afcd';
    }

    public function sourceCode(): string
    {
        return 'afcd';
    }

    public function files(): array
    {
        return config('generic-food-sources.sources.afcd.files');
    }

    public function records(array $paths): Generator
    {
        $this->ensureMemory();
        $descriptions = $this->foodDescriptions($paths['foods']);
        $reader = IOFactory::createReaderForFile($paths['nutrients']);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly([
            'All solids & liquids per 100 g',
            'Liquids only per 100 mL',
        ]);
        $workbook = $reader->load($paths['nutrients']);
        $liquidSheet = $workbook->getSheetByName(
            'Liquids only per 100 mL'
        );
        $liquidIds = $this->foodIds($liquidSheet);
        $solidSheet = $workbook->getSheetByName(
            'All solids & liquids per 100 g'
        );

        yield from $this->sheetRecords(
            $solidSheet,
            'g',
            $liquidIds,
            $descriptions
        );
        yield from $this->sheetRecords(
            $liquidSheet,
            'ml',
            descriptions: $descriptions
        );
    }

    /**
     * @param  array<string, true>  $skipIds
     * @param  array<string, string>  $descriptions
     * @return Generator<int, array<string, mixed>>
     */
    private function sheetRecords(
        ?Worksheet $sheet,
        string $basis,
        array $skipIds = [],
        array $descriptions = []
    ): Generator {
        if ($sheet === null) {
            return;
        }

        $headers = $this->headers($sheet);

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $id = $this->text($sheet->getCell([$headers['id'], $row])
                ->getCalculatedValue(), 64);

            if ($id === null || isset($skipIds[$id])) {
                continue;
            }

            $name = $this->text(
                $sheet->getCell([$headers['name'], $row])
                    ->getCalculatedValue()
            );

            if ($name === null) {
                continue;
            }

            $energyKj = $this->cellNumber(
                $sheet,
                $headers['energy'],
                $row
            );
            $nutrients = [
                'calories' => $energyKj === null
                    ? null
                    : round($energyKj / 4.184, 3),
                'protein' => $this->cellNumber(
                    $sheet,
                    $headers['protein'],
                    $row
                ),
                'fat' => $this->cellNumber(
                    $sheet,
                    $headers['fat'],
                    $row
                ),
                'carbohydrates' => $this->cellNumber(
                    $sheet,
                    $headers['carbohydrates'],
                    $row
                ),
                'fibre' => $this->cellNumber(
                    $sheet,
                    $headers['fibre'],
                    $row
                ),
                'sugar' => $this->cellNumber(
                    $sheet,
                    $headers['sugar'],
                    $row
                ),
                'sodium_mg' => $this->cellNumber(
                    $sheet,
                    $headers['sodium'],
                    $row
                ),
                'saturated_fat' => $this->cellNumber(
                    $sheet,
                    $headers['saturated_fat'],
                    $row
                ),
            ];
            $record = $this->record(
                externalId: $id.':'.$basis,
                name: $name,
                nutrients: $nutrients,
                basisUnit: $basis,
                extraSearchText: $descriptions[$id] ?? null
            );

            if ($record !== null) {
                yield $record;
            }
        }
    }

    /**
     * @return array<string, string>
     */
    private function foodDescriptions(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['Food details']);
        $sheet = $reader->load($path)->getActiveSheet();
        $descriptions = [];

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $id = $this->text(
                $sheet->getCell([1, $row])->getCalculatedValue(),
                64
            );
            $description = $this->text(
                $sheet->getCell([5, $row])->getCalculatedValue(),
                2000
            );

            if ($id !== null && $description !== null) {
                $descriptions[$id] = $description;
            }
        }

        return $descriptions;
    }

    /**
     * @return array<string, int>
     */
    private function headers(Worksheet $sheet): array
    {
        $map = [
            'id' => 'Public Food Key',
            'name' => 'Food Name',
            'energy' => 'Energy with dietary fibre',
            'protein' => 'Protein',
            'fat' => 'Fat, total',
            'carbohydrates' => 'Available carbohydrate, without sugar alcohols',
            'fibre' => 'Total dietary fibre',
            'sugar' => 'Total sugars',
            'sodium' => 'Sodium',
            'saturated_fat' => 'Total saturated fatty acids, equated',
        ];
        $headers = [];

        $highestColumn = Coordinate::columnIndexFromString(
            $sheet->getHighestDataColumn(3)
        );

        for ($column = 1; $column <= $highestColumn; $column++) {
            $value = preg_replace(
                '/\s+/u',
                ' ',
                trim((string) $sheet->getCell([$column, 3])->getValue())
            );

            foreach ($map as $key => $needle) {
                if (
                    ! isset($headers[$key])
                    && str_starts_with(
                        mb_strtolower($value),
                        mb_strtolower($needle)
                    )
                ) {
                    $headers[$key] = $column;
                }
            }
        }

        return $headers;
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
     * @return array<string, true>
     */
    private function foodIds(?Worksheet $sheet): array
    {
        if ($sheet === null) {
            return [];
        }

        $ids = [];

        for ($row = 4; $row <= $sheet->getHighestDataRow(); $row++) {
            $id = $this->text(
                $sheet->getCell([1, $row])->getCalculatedValue(),
                64
            );

            if ($id !== null) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function cellNumber(
        Worksheet $sheet,
        int $column,
        int $row
    ): ?float {
        return $this->number(
            $sheet->getCell([$column, $row])->getCalculatedValue()
        );
    }
}
