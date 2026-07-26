<?php

namespace App\Console\Commands;

use App\Services\FoodCatalog\CatalogImporter;
use App\Services\FoodCatalog\CatalogSource;
use App\Services\FoodCatalog\RemoteFileDownloader;
use App\Services\FoodCatalog\Sources\AfcdSource;
use App\Services\FoodCatalog\Sources\CanadianNutrientFileSource;
use App\Services\FoodCatalog\Sources\CofidSource;
use App\Services\FoodCatalog\Sources\FineliSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ImportGenericFoodSources extends Command
{
    protected $signature = 'foods:import-generic-sources
        {--source=all : cnf, fineli, cofid, afcd, or all}
        {--batch= : Foods written per transaction}
        {--dry-run : Parse and validate without writing}
        {--force : Reimport source files already completed}
        {--force-download : Download source files again}
        {--download-only : Download files without importing}';

    protected $description =
        'Download and import official generic food composition datasets';

    public function handle(
        CatalogImporter $importer,
        RemoteFileDownloader $downloader
    ): int {
        try {
            $sources = $this->sources((string) $this->option('source'));
            $lock = Cache::lock('generic-food-sources-import', 60 * 60);

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another generic food import is already running.'
                );
            }

            try {
                foreach ($sources as $source) {
                    $paths = $this->download($source, $downloader);

                    if ($this->option('download-only')) {
                        continue;
                    }

                    $this->importSource($source, $paths, $importer);
                }
            } finally {
                $lock->release();
            }

            $message = $this->option('download-only')
                ? 'Generic food source downloads finished.'
                : 'Generic food source imports finished.';
            $this->components->success($message);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, CatalogSource>
     */
    private function sources(string $requested): array
    {
        $classes = [
            'cnf' => CanadianNutrientFileSource::class,
            'fineli' => FineliSource::class,
            'cofid' => CofidSource::class,
            'afcd' => AfcdSource::class,
        ];

        if ($requested !== 'all' && ! isset($classes[$requested])) {
            throw new RuntimeException(
                'Source must be cnf, fineli, cofid, afcd, or all.'
            );
        }

        $selected = $requested === 'all'
            ? $classes
            : [$requested => $classes[$requested]];

        return array_map(
            fn (string $class) => app($class),
            array_values($selected)
        );
    }

    /**
     * @return array<string, string>
     */
    private function download(
        CatalogSource $source,
        RemoteFileDownloader $downloader
    ): array {
        $paths = [];

        foreach ($source->files() as $name => $file) {
            $paths[$name] = $downloader->ensureAvailable(
                "{$source->key()}:{$name}",
                $file,
                (bool) $this->option('force-download'),
                fn (string $message) => $this->line($message)
            );
        }

        return $paths;
    }

    /**
     * @param  array<string, string>  $paths
     */
    private function importSource(
        CatalogSource $source,
        array $paths,
        CatalogImporter $importer
    ): void {
        $this->components->info("Importing {$source->key()}");
        $lastReported = -1;
        $stats = $importer->import(
            source: $source,
            paths: $paths,
            batchSize: $this->batchSize(),
            dryRun: (bool) $this->option('dry-run'),
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
            ['Source', 'Status', 'Processed', 'Inserted', 'Updated', 'Skipped'],
            [[
                $source->key(),
                $stats['status'],
                number_format($stats['processed']),
                number_format($stats['inserted']),
                number_format($stats['updated']),
                number_format($stats['skipped']),
            ]]
        );
    }

    private function batchSize(): int
    {
        $value = $this->option('batch')
            ?? config('generic-food-sources.batch_size', 500);

        if (! is_numeric($value) || (int) $value != $value) {
            throw new RuntimeException('--batch must be an integer.');
        }

        return (int) $value;
    }
}
