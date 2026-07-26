<?php

namespace App\Services\FoodCatalog;

use Generator;
use RuntimeException;
use ZipArchive;

class ZipCsvReader
{
    /**
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(
        string $archivePath,
        string $fileName,
        string $delimiter = ','
    ): Generator {
        $archive = new ZipArchive;

        if ($archive->open($archivePath) !== true) {
            throw new RuntimeException(
                "Unable to open ZIP archive: {$archivePath}"
            );
        }

        $stream = $archive->getStream($fileName);

        if ($stream === false) {
            $archive->close();
            throw new RuntimeException(
                "{$fileName} is missing from {$archivePath}."
            );
        }

        try {
            $header = fgetcsv($stream, separator: $delimiter);

            if (! is_array($header)) {
                return;
            }

            $header = array_map(
                fn ($value) => $this->cleanHeader((string) $value),
                $header
            );

            while (($values = fgetcsv($stream, separator: $delimiter)) !== false) {
                $values = array_pad($values, count($header), null);
                $row = [];

                foreach ($header as $index => $column) {
                    $row[$column] = isset($values[$index])
                        ? trim((string) $values[$index])
                        : null;
                }

                yield $row;
            }
        } finally {
            fclose($stream);
            $archive->close();
        }
    }

    private function cleanHeader(string $value): string
    {
        return trim($value, "\xEF\xBB\xBF \t\n\r\0\x0B");
    }
}
