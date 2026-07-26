<?php

namespace App\Console\Commands;

use App\Services\UsdaFoodData\ArchiveDownloader;
use App\Services\UsdaFoodData\FoodImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ImportUsdaFoods extends Command
{
    protected $signature = 'foods:import-usda
        {--dataset=all : foundation, sr-legacy, or all}
        {--batch= : Foods written per transaction}
        {--dry-run : Validate without writing}
        {--resume : Resume an interrupted import}
        {--force : Reimport archives already completed}';

    protected $description = 'Import USDA Foundation and SR Legacy generic foods';

    public function handle(
        FoodImporter $importer,
        ArchiveDownloader $downloader
    ): int {
        try {
            if ($this->option('resume') && $this->option('force')) {
                throw new RuntimeException(
                    'Use either --resume or --force, not both.'
                );
            }

            $datasets = $this->datasets((string) $this->option('dataset'));
            $batchSize = $this->batchSize();
            $this->ensureArchives($datasets, $downloader);
            $lock = Cache::lock('usda-foods-import', 60 * 60);

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another USDA food import is already running.'
                );
            }

            try {
                foreach ($datasets as $name => $settings) {
                    $this->importDataset(
                        $importer,
                        $name,
                        $settings,
                        $batchSize
                    );
                }
            } finally {
                $lock->release();
            }

            $this->components->success('USDA generic food import finished.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @param  array<string, array<string, string>>  $datasets
     */
    private function ensureArchives(
        array $datasets,
        ArchiveDownloader $downloader
    ): void {
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
                    status: fn (string $message) => $this->line($message)
                );
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array<string, string>  $settings
     */
    private function importDataset(
        FoodImporter $importer,
        string $name,
        array $settings,
        int $batchSize
    ): void {
        $this->components->info("Importing USDA {$name}");
        $lastReported = -1;
        $stats = $importer->import(
            path: $settings['path'],
            dataset: $name,
            rootKey: $settings['root_key'],
            batchSize: $batchSize,
            dryRun: (bool) $this->option('dry-run'),
            resume: (bool) $this->option('resume'),
            force: (bool) $this->option('force'),
            progress: function (array $stats) use (&$lastReported): void {
                if ($lastReported === $stats['processed']) {
                    return;
                }

                $lastReported = $stats['processed'];
                $this->line(sprintf(
                    'Processed %s · eligible %s · inserted %s · updated %s · skipped %s',
                    number_format($stats['processed']),
                    number_format($stats['eligible']),
                    number_format($stats['inserted']),
                    number_format($stats['updated']),
                    number_format($stats['skipped'])
                ));
            }
        );

        $this->table(
            ['Dataset', 'Status', 'Processed', 'Inserted', 'Updated', 'Skipped'],
            [[
                $name,
                $stats['status'],
                number_format($stats['processed']),
                number_format($stats['inserted']),
                number_format($stats['updated']),
                number_format($stats['skipped']),
            ]]
        );
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
                'Dataset must be foundation, sr-legacy, or all.'
            );
        }

        return [$requested => $datasets[$requested]];
    }

    private function batchSize(): int
    {
        $value = $this->option('batch')
            ?? config('usda-food-data.batch_size', 500);

        if (! is_numeric($value) || (int) $value != $value) {
            throw new RuntimeException('--batch must be an integer.');
        }

        return (int) $value;
    }
}
