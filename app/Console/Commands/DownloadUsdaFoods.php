<?php

namespace App\Console\Commands;

use App\Services\UsdaFoodData\ArchiveDownloader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class DownloadUsdaFoods extends Command
{
    protected $signature = 'foods:download-usda
        {--dataset=all : foundation, sr-legacy, fndds, or all}
        {--force : Replace archives that already exist}';

    protected $description = 'Download USDA generic food archives atomically';

    public function handle(ArchiveDownloader $downloader): int
    {
        try {
            $datasets = $this->datasets((string) $this->option('dataset'));
            $lock = Cache::lock('usda-foods-download', 60 * 60);

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another USDA food download is already running.'
                );
            }

            try {
                foreach ($datasets as $name => $settings) {
                    $downloader->ensureAvailable(
                        $name,
                        $settings,
                        (bool) $this->option('force'),
                        fn (string $message) => $this->line($message)
                    );
                }
            } finally {
                $lock->release();
            }

            $this->components->success('USDA food downloads are ready.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function datasets(string $requested): array
    {
        $datasets = config('usda-food-data.datasets', []);

        if ($requested === 'all') {
            return $datasets;
        }

        if (! isset($datasets[$requested])) {
            throw new RuntimeException(
                'Dataset must be foundation, sr-legacy, fndds, or all.'
            );
        }

        return [$requested => $datasets[$requested]];
    }
}
