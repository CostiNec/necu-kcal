<?php

namespace App\Services\UsdaFoodData;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use ZipArchive;

class ArchiveDownloader
{
    /**
     * @param  array{url: string, path: string, root_key: string}  $settings
     * @param  callable(string): void|null  $status
     */
    public function ensureAvailable(
        string $dataset,
        array $settings,
        bool $force = false,
        ?callable $status = null
    ): string {
        $path = $settings['path'];

        if (is_file($path) && ! $force) {
            $this->assertZip($path);
            $status?->__invoke(
                "Using existing {$dataset} archive: {$path}"
            );

            return $path;
        }

        File::ensureDirectoryExists(dirname($path));
        $partial = $path.'.part';
        File::delete($partial);
        $status?->__invoke("Downloading {$dataset} to {$path}…");

        try {
            Http::withOptions(['sink' => $partial])
                ->connectTimeout(30)
                ->timeout(0)
                ->retry(3, 2000)
                ->get($settings['url'])
                ->throw();
            $this->assertZip($partial);

            if (! rename($partial, $path)) {
                throw new RuntimeException(
                    "Unable to move the {$dataset} archive into place."
                );
            }
        } finally {
            File::delete($partial);
        }

        return $path;
    }

    private function assertZip(string $path): void
    {
        $archive = new ZipArchive;

        if ($archive->open($path) !== true) {
            throw new RuntimeException(
                "USDA archive is not a valid ZIP file: {$path}"
            );
        }

        $archive->close();
    }
}
