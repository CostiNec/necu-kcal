<?php

namespace App\Services\FoodCatalog;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class RemoteFileDownloader
{
    /**
     * @param  array{url: string, path: string}  $file
     * @param  callable(string): void|null  $status
     */
    public function ensureAvailable(
        string $label,
        array $file,
        bool $force = false,
        ?callable $status = null
    ): string {
        $path = $file['path'];

        if (is_file($path) && ! $force) {
            $this->assertFile($path);
            $status?->__invoke("Using existing {$label}: {$path}");

            return $path;
        }

        File::ensureDirectoryExists(dirname($path));
        $partial = $path.'.part';
        File::delete($partial);
        $status?->__invoke("Downloading {$label} to {$path}…");

        try {
            Http::withOptions(['sink' => $partial])
                ->connectTimeout(30)
                ->timeout(0)
                ->retry(3, 2000)
                ->get($file['url'])
                ->throw();
            $this->assertFile(
                $partial,
                strtolower(pathinfo($path, PATHINFO_EXTENSION))
            );

            if (! rename($partial, $path)) {
                throw new RuntimeException(
                    "Unable to move the downloaded {$label} file."
                );
            }
        } finally {
            File::delete($partial);
        }

        return $path;
    }

    private function assertFile(
        string $path,
        ?string $expectedExtension = null
    ): void {
        if (! is_readable($path) || filesize($path) < 100) {
            throw new RuntimeException(
                "Downloaded food source is empty or unreadable: {$path}"
            );
        }

        $extension = $expectedExtension
            ?? strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['zip', 'xlsx'], true)) {
            return;
        }

        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException(
                "Downloaded food source is not a valid {$extension} archive: {$path}"
            );
        }

        $archive->close();
    }
}
