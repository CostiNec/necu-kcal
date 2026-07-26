<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class DownloadOpenFoodFacts extends Command
{
    protected $signature = 'foods:download-open-food-facts
        {path? : Destination path for the JSONL.GZ dump}
        {--url= : Override the Open Food Facts download URL}
        {--force : Replace an existing dump after a successful download}';

    protected $description = 'Download the Open Food Facts product dump atomically';

    public function handle(): int
    {
        $partialPath = null;

        try {
            $url = (string) (
                $this->option('url')
                ?: config('open-food-facts.download_url')
            );
            $path = $this->resolvePath(
                $this->argument('path')
                    ?: config('open-food-facts.import_path')
            );

            if ($url === '') {
                throw new RuntimeException('A download URL is required.');
            }

            if (is_file($path) && ! $this->option('force')) {
                throw new RuntimeException(
                    "The dump already exists at {$path}. Use --force to replace it."
                );
            }

            File::ensureDirectoryExists(dirname($path));
            $partialPath = $path.'.part';
            File::delete($partialPath);
            $lock = Cache::lock(
                'open-food-facts-download',
                60 * 60 * 24
            );

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another Open Food Facts download is already running.'
                );
            }

            try {
                $this->components->info(
                    "Downloading Open Food Facts to {$path}"
                );

                Http::withOptions(['sink' => $partialPath])
                    ->connectTimeout(30)
                    ->timeout(0)
                    ->retry(3, 2000)
                    ->get($url)
                    ->throw();

                $this->assertGzipJsonl($partialPath);

                if (! rename($partialPath, $path)) {
                    throw new RuntimeException(
                        'The downloaded file could not be moved into place.'
                    );
                }
            } finally {
                $lock->release();
            }

            $this->components->success(
                sprintf(
                    'Download complete: %s (%s)',
                    $path,
                    $this->formatBytes(filesize($path) ?: 0)
                )
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            if ($partialPath !== null) {
                File::delete($partialPath);
            }

            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePath(mixed $path): string
    {
        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('A destination path is required.');
        }

        $path = trim($path);

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function assertGzipJsonl(string $path): void
    {
        $handle = gzopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException(
                'The downloaded file is not a readable gzip archive.'
            );
        }

        try {
            $line = gzgets($handle);
        } finally {
            gzclose($handle);
        }

        if (
            ! is_string($line)
            || ! is_array(json_decode(trim($line), true))
        ) {
            throw new RuntimeException(
                'The downloaded archive does not contain JSONL product data.'
            );
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $unit = 0;
        $size = (float) $bytes;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.1f %s', $size, $units[$unit]);
    }
}
