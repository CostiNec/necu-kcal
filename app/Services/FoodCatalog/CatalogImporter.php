<?php

namespace App\Services\FoodCatalog;

use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class CatalogImporter
{
    public function __construct(
        private readonly FoodRecordPersister $persister
    ) {}

    /**
     * @param  array<string, string>  $paths
     * @param  callable(array<string, mixed>): void|null  $progress
     * @return array<string, mixed>
     */
    public function import(
        CatalogSource $source,
        array $paths,
        int $batchSize = 500,
        bool $dryRun = false,
        bool $force = false,
        ?callable $progress = null
    ): array {
        if ($batchSize < 1 || $batchSize > 1000) {
            throw new RuntimeException(
                'Batch size must be between 1 and 1000.'
            );
        }

        $sourceId = DB::table('food_sources')
            ->where('code', $source->sourceCode())
            ->value('id');

        if ($sourceId === null) {
            throw new RuntimeException(
                "{$source->sourceCode()} is missing. Run migrations first."
            );
        }

        $fingerprint = $this->fingerprint($source, $paths);
        $runId = $dryRun
            ? null
            : $this->startRun((int) $sourceId, $fingerprint, $force);
        $stats = [
            'run_id' => $runId,
            'status' => $dryRun ? 'dry-run' : 'running',
            'processed' => 0,
            'eligible' => 0,
            'inserted' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => 0,
            'skip_reasons' => [],
        ];
        $batch = [];
        $progressEvery = max(
            1,
            (int) config(
                'generic-food-sources.progress_every',
                1000
            )
        );

        DB::disableQueryLog();

        try {
            foreach ($source->records($paths) as $record) {
                $stats['processed']++;

                if (
                    ! isset($record['external_id'], $record['food'])
                    || ! is_array($record['food'])
                ) {
                    $stats['skipped']++;
                    $stats['skip_reasons']['invalid_record'] =
                        ($stats['skip_reasons']['invalid_record'] ?? 0) + 1;
                } else {
                    $stats['eligible']++;

                    if (! $dryRun) {
                        $batch[] = $record;
                    }
                }

                if (
                    count($batch) >= $batchSize
                    || $stats['processed'] % $progressEvery === 0
                ) {
                    $this->checkpoint(
                        $batch,
                        (int) $sourceId,
                        $source->sourceCode(),
                        $stats,
                        $dryRun,
                        $progress
                    );
                }
            }

            if ($batch !== []) {
                $this->checkpoint(
                    $batch,
                    (int) $sourceId,
                    $source->sourceCode(),
                    $stats,
                    $dryRun,
                    $progress
                );
            }

            $stats['status'] = $dryRun ? 'dry-run' : 'completed';

            if (! $dryRun) {
                $this->updateRun($stats, ['finished_at' => now()]);
            }

            $progress?->__invoke($stats);

            return $stats;
        } catch (Throwable $exception) {
            if ($runId !== null) {
                DB::table('food_import_runs')
                    ->where('id', $runId)
                    ->update([
                        'status' => 'failed',
                        'error_message' => mb_substr(
                            $exception->getMessage(),
                            0,
                            60000
                        ),
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @param  array<string, mixed>  $stats
     */
    private function checkpoint(
        array &$batch,
        int $sourceId,
        string $sourceCode,
        array &$stats,
        bool $dryRun,
        ?callable $progress
    ): void {
        if (! $dryRun && $batch !== []) {
            DB::transaction(function () use (
                &$batch,
                $sourceId,
                $sourceCode,
                &$stats
            ): void {
                $counts = $this->persister->persist(
                    $batch,
                    $sourceId,
                    $sourceCode
                );
                $stats['inserted'] += $counts['inserted'];
                $stats['updated'] += $counts['updated'];
                $this->updateRun($stats);
            });
            $batch = [];
        } elseif (! $dryRun) {
            $this->updateRun($stats);
        }

        $progress?->__invoke($stats);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @param  array<string, mixed>  $extra
     */
    private function updateRun(array $stats, array $extra = []): void
    {
        DB::table('food_import_runs')
            ->where('id', $stats['run_id'])
            ->update([
                'status' => $stats['status'],
                'processed_count' => $stats['processed'],
                'inserted_count' => $stats['inserted'],
                'updated_count' => $stats['updated'],
                'skipped_count' => $stats['skipped'],
                'error_count' => $stats['errors'],
                'last_processed_line' => $stats['processed'],
                'skip_reasons' => json_encode(
                    $stats['skip_reasons'],
                    JSON_THROW_ON_ERROR
                ),
                'updated_at' => now(),
                ...$extra,
            ]);
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     */
    private function startRun(
        int $sourceId,
        array $fingerprint,
        bool $force
    ): int {
        if (! $force) {
            $alreadyImported = DB::table('food_import_runs')
                ->where('source_id', $sourceId)
                ->where('status', 'completed')
                ->latest('id')
                ->get()
                ->contains(function (object $run) use ($fingerprint): bool {
                    try {
                        return json_decode(
                            (string) $run->options,
                            true,
                            flags: JSON_THROW_ON_ERROR
                        ) === $fingerprint;
                    } catch (JsonException) {
                        return false;
                    }
                });

            if ($alreadyImported) {
                throw new RuntimeException(
                    'These source files were already imported. Use --force to reimport them.'
                );
            }
        }

        return DB::table('food_import_runs')->insertGetId([
            'source_id' => $sourceId,
            'status' => 'running',
            'file_name' => $fingerprint['file_name'],
            'options' => json_encode($fingerprint, JSON_THROW_ON_ERROR),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, string>  $paths
     * @return array<string, mixed>
     */
    private function fingerprint(
        CatalogSource $source,
        array $paths
    ): array {
        $files = [];

        foreach ($paths as $name => $path) {
            $files[$name] = [
                'name' => basename($path),
                'size' => filesize($path) ?: 0,
                'modified_at' => filemtime($path) ?: 0,
            ];
        }

        return [
            'source' => $source->key(),
            'file_name' => collect($paths)
                ->map(fn (string $path) => basename($path))
                ->implode('+'),
            'files' => $files,
        ];
    }
}
