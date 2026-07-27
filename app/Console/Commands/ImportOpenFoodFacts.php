<?php

namespace App\Console\Commands;

use App\Services\OpenFoodFacts\ProductImporter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class ImportOpenFoodFacts extends Command
{
    protected $signature = 'foods:import-open-food-facts
        {path? : Path to the Open Food Facts JSONL or JSONL.GZ dump}
        {--scope=ro : Import products sold in Romania (ro) or every product (all)}
        {--batch= : Number of eligible products written per transaction}
        {--limit= : Maximum source records to read during this run}
        {--dry-run : Validate and map records without writing to the database}
        {--resume : Resume the latest interrupted run for this file}
        {--force : Import even when this exact dump was already completed}';

    protected $description = 'Stream an Open Food Facts dump into the foods database';

    public function handle(ProductImporter $importer): int
    {
        try {
            $path = $this->resolvePath(
                $this->argument('path')
                    ?: config('open-food-facts.import_path')
            );
            $scope = (string) $this->option('scope');
            $batchSize = $this->integerOption(
                'batch',
                (int) config('open-food-facts.batch_size', 500)
            );
            $limit = $this->nullableIntegerOption('limit');
            $dryRun = (bool) $this->option('dry-run');
            $resume = (bool) $this->option('resume');
            $force = (bool) $this->option('force');

            if ($resume && $force) {
                throw new RuntimeException(
                    'Use either --resume or --force, not both.'
                );
            }

            $lock = Cache::lock(
                'open-food-facts-import',
                60 * 60 * 24 * 7
            );

            if ( $lock->get()) {
                throw new LockTimeoutException(
                    'Another Open Food Facts import is already running.'
                );
            }

            try {
                $this->components->info(
                    sprintf(
                        '%s Open Food Facts from %s',
                        $dryRun ? 'Checking' : 'Importing',
                        $path
                    )
                );
                $this->line(
                    sprintf(
                        'Scope: %s · batch: %s · file: %s',
                        strtoupper($scope),
                        number_format($batchSize),
                        $this->formatBytes(filesize($path) ?: 0)
                    )
                );

                $reportEvery = max(
                    1,
                    (int) config(
                        'open-food-facts.progress_every',
                        10000
                    )
                );
                $lastReported = 0;
                $stats = $importer->import(
                    path: $path,
                    scope: $scope,
                    batchSize: $batchSize,
                    limit: $limit,
                    dryRun: $dryRun,
                    resume: $resume,
                    force: $force,
                    progress: function (array $current) use (
                        &$lastReported,
                        $reportEvery
                    ): void {
                        $processed = (int) $current['processed'];

                        if ($processed === $lastReported) {
                            return;
                        }

                        if (
                            $processed - $lastReported < $reportEvery
                            && $current['status'] === 'running'
                        ) {
                            return;
                        }

                        $lastReported = $processed;
                        $this->line(
                            sprintf(
                                'Processed %s · eligible %s · inserted %s · updated %s · skipped %s · errors %s',
                                number_format($processed),
                                number_format(
                                    (int) $current['eligible']
                                ),
                                number_format(
                                    (int) $current['inserted']
                                ),
                                number_format(
                                    (int) $current['updated']
                                ),
                                number_format(
                                    (int) $current['skipped']
                                ),
                                number_format(
                                    (int) $current['errors']
                                )
                            )
                        );

                        if ($current['skip_reasons'] !== []) {
                            $this->line(
                                '  Skip reasons: '
                                .$this->formatSkipReasons(
                                    $current['skip_reasons']
                                )
                            );
                        }
                    }
                );
            } finally {
                $lock->release();
            }

            $this->newLine();
            $this->table(
                ['Status', 'Processed', 'Eligible', 'Inserted', 'Updated', 'Skipped', 'Errors'],
                [[
                    $stats['status'],
                    number_format((int) $stats['processed']),
                    number_format((int) $stats['eligible']),
                    number_format((int) $stats['inserted']),
                    number_format((int) $stats['updated']),
                    number_format((int) $stats['skipped']),
                    number_format((int) $stats['errors']),
                ]]
            );

            if ($stats['skip_reasons'] !== []) {
                $this->newLine();
                $this->table(
                    ['Skip reason', 'Records'],
                    collect($stats['skip_reasons'])
                        ->sortDesc()
                        ->map(
                            fn (int $count, string $reason) => [
                                $this->skipReasonLabel($reason),
                                number_format($count),
                            ]
                        )
                        ->values()
                        ->all()
                );
            }

            $this->components->success(
                $dryRun
                    ? 'Dry run finished; the database was not changed.'
                    : 'Open Food Facts import finished.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePath(mixed $path): string
    {
        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException('An import path is required.');
        }

        $path = trim($path);

        if (! str_starts_with($path, DIRECTORY_SEPARATOR)) {
            $path = base_path($path);
        }

        return $path;
    }

    private function integerOption(string $name, int $default): int
    {
        $value = $this->option($name);

        if ($value === null) {
            return $default;
        }

        if (! is_numeric($value) || (int) $value != $value) {
            throw new RuntimeException("--{$name} must be an integer.");
        }

        return (int) $value;
    }

    private function nullableIntegerOption(string $name): ?int
    {
        $value = $this->option($name);

        if ($value === null) {
            return null;
        }

        if (! is_numeric($value) || (int) $value != $value) {
            throw new RuntimeException("--{$name} must be an integer.");
        }

        return (int) $value;
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

    /**
     * @param  array<string, int>  $reasons
     */
    private function formatSkipReasons(array $reasons): string
    {
        arsort($reasons);

        return collect($reasons)
            ->map(
                fn (int $count, string $reason) => sprintf(
                    '%s %s',
                    $this->skipReasonLabel($reason),
                    number_format($count)
                )
            )
            ->implode(' · ');
    }

    private function skipReasonLabel(string $reason): string
    {
        return match ($reason) {
            'missing_source_id' => 'missing source ID',
            'missing_name' => 'missing name',
            'missing_energy' => 'missing/invalid energy',
            'liquid_product' => 'liquid product',
            'outside_scope' => 'outside scope',
            default => str_replace('_', ' ', $reason),
        };
    }
}
