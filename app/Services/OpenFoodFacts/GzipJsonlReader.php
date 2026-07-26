<?php

namespace App\Services\OpenFoodFacts;

use Generator;
use RuntimeException;

class GzipJsonlReader
{
    /**
     * @return Generator<int, array{line: int, json: string}>
     */
    public function read(string $path, int $skipLines = 0): Generator
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException("Unable to open Open Food Facts dump: {$path}");
        }

        $lineNumber = 0;

        try {
            while (! gzeof($handle)) {
                $line = gzgets($handle);

                if ($line === false) {
                    break;
                }

                $lineNumber++;

                if ($lineNumber <= $skipLines) {
                    continue;
                }

                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                yield [
                    'line' => $lineNumber,
                    'json' => $line,
                ];
            }
        } finally {
            gzclose($handle);
        }
    }
}
