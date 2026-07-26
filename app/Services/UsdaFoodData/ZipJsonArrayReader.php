<?php

namespace App\Services\UsdaFoodData;

use Generator;
use JsonException;
use RuntimeException;
use ZipArchive;

class ZipJsonArrayReader
{
    /**
     * @return Generator<int, array{line: int, product: array<string, mixed>}>
     */
    public function read(
        string $path,
        string $rootKey,
        int $skipItems = 0
    ): Generator {
        $entry = $this->jsonEntry($path);
        $stream = fopen("zip://{$path}#{$entry}", 'rb');

        if ($stream === false) {
            throw new RuntimeException(
                "Unable to read {$entry} from the USDA archive."
            );
        }

        $pattern = '"'.$rootKey.'"';
        $header = '';
        $arrayStarted = false;
        $objectStarted = false;
        $depth = 0;
        $inString = false;
        $escaped = false;
        $buffer = '';
        $item = 0;

        try {
            while (! feof($stream)) {
                $chunk = fread($stream, 65536);

                if ($chunk === false) {
                    throw new RuntimeException(
                        'Unable to read the USDA JSON stream.'
                    );
                }

                $length = strlen($chunk);

                for ($index = 0; $index < $length; $index++) {
                    $character = $chunk[$index];

                    if (! $arrayStarted) {
                        $header .= $character;

                        if (strlen($header) > strlen($pattern) + 32) {
                            $header = substr(
                                $header,
                                -(strlen($pattern) + 32)
                            );
                        }

                        if (
                            str_contains($header, $pattern)
                            && $character === '['
                        ) {
                            $arrayStarted = true;
                        }

                        continue;
                    }

                    if (! $objectStarted) {
                        if ($character === ']') {
                            return;
                        }

                        if ($character !== '{') {
                            continue;
                        }

                        $objectStarted = true;
                        $depth = 1;
                        $buffer = '{';

                        continue;
                    }

                    $buffer .= $character;

                    if ($inString) {
                        if ($escaped) {
                            $escaped = false;
                        } elseif ($character === '\\') {
                            $escaped = true;
                        } elseif ($character === '"') {
                            $inString = false;
                        }

                        continue;
                    }

                    if ($character === '"') {
                        $inString = true;

                        continue;
                    }

                    if ($character === '{' || $character === '[') {
                        $depth++;
                    } elseif ($character === '}' || $character === ']') {
                        $depth--;
                    }

                    if ($depth !== 0) {
                        continue;
                    }

                    $item++;
                    $objectStarted = false;

                    if ($item <= $skipItems) {
                        $buffer = '';

                        continue;
                    }

                    try {
                        $product = json_decode(
                            $buffer,
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException $exception) {
                        throw new RuntimeException(
                            "Invalid USDA JSON object at item {$item}.",
                            previous: $exception
                        );
                    }

                    $buffer = '';

                    if (! is_array($product)) {
                        throw new RuntimeException(
                            "Invalid USDA record at item {$item}."
                        );
                    }

                    yield [
                        'line' => $item,
                        'product' => $product,
                    ];
                }
            }
        } finally {
            fclose($stream);
        }

        if (! $arrayStarted) {
            throw new RuntimeException(
                "The USDA archive does not contain the {$rootKey} array."
            );
        }

        if ($objectStarted) {
            throw new RuntimeException(
                'The USDA archive contains an incomplete record at item '
                .($item + 1).'.'
            );
        }
    }

    private function jsonEntry(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("USDA archive is not readable: {$path}");
        }

        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException("Invalid USDA ZIP archive: {$path}");
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $name = $archive->getNameIndex($index);

                if (
                    is_string($name)
                    && str_ends_with(mb_strtolower($name), '.json')
                ) {
                    return $name;
                }
            }
        } finally {
            $archive->close();
        }

        throw new RuntimeException(
            'The USDA archive does not contain a JSON file.'
        );
    }
}
