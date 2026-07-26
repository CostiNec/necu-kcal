<?php

namespace App\Services\UsdaFoodData;

use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class FoodImporter
{
    public function __construct(
        private readonly ZipJsonArrayReader $reader,
        private readonly FoodMapper $mapper
    ) {}

    /**
     * @param  callable(array<string, mixed>): void|null  $progress
     * @return array<string, mixed>
     */
    public function import(
        string $path,
        string $dataset,
        string $rootKey,
        int $batchSize = 500,
        bool $dryRun = false,
        bool $resume = false,
        bool $force = false,
        ?callable $progress = null
    ): array {
        $this->validate($path, $batchSize);
        $sourceId = DB::table('food_sources')
            ->where('code', config('usda-food-data.source_code'))
            ->value('id');

        if ($sourceId === null) {
            throw new RuntimeException(
                'USDA FoodData Central source is missing. Run migrations first.'
            );
        }

        $run = $dryRun
            ? null
            : $this->startRun(
                (int) $sourceId,
                $path,
                $dataset,
                $resume,
                $force
            );
        $stats = [
            'run_id' => $run?->id,
            'status' => $dryRun ? 'dry-run' : 'running',
            'processed' => (int) ($run?->processed_count ?? 0),
            'eligible' => (int) ($run?->inserted_count ?? 0)
                + (int) ($run?->updated_count ?? 0),
            'inserted' => (int) ($run?->inserted_count ?? 0),
            'updated' => (int) ($run?->updated_count ?? 0),
            'skipped' => (int) ($run?->skipped_count ?? 0),
            'errors' => (int) ($run?->error_count ?? 0),
            'last_line' => (int) ($run?->last_processed_line ?? 0),
            'skip_reasons' => $this->skipReasons($run?->skip_reasons ?? null),
        ];
        $batch = [];
        $progressEvery = max(
            1,
            (int) config('usda-food-data.progress_every', 1000)
        );

        DB::disableQueryLog();

        try {
            foreach (
                $this->reader->read($path, $rootKey, $stats['last_line']) as $item
            ) {
                $stats['processed']++;
                $stats['last_line'] = $item['line'];
                $result = $this->mapper->mapWithReason($item['product']);
                $mapped = $result['product'];

                if ($mapped === null) {
                    $stats['skipped']++;
                    $reason = $result['skipped_reason'] ?? 'unknown';
                    $stats['skip_reasons'][$reason] =
                        ($stats['skip_reasons'][$reason] ?? 0) + 1;
                } else {
                    $stats['eligible']++;

                    if (! $dryRun) {
                        $batch[] = $mapped;
                    }
                }

                if (
                    count($batch) < $batchSize
                    && $stats['processed'] % $progressEvery !== 0
                ) {
                    continue;
                }

                $this->checkpoint(
                    $batch,
                    (int) $sourceId,
                    $stats,
                    $dryRun,
                    $progress
                );
            }

            if ($batch !== []) {
                $this->checkpoint(
                    $batch,
                    (int) $sourceId,
                    $stats,
                    $dryRun,
                    $progress
                );
            }

            $stats['status'] = $dryRun ? 'dry-run' : 'completed';

            if (! $dryRun) {
                $this->updateRun($stats, ['finished_at' => now()]);
            }

            if ($progress !== null) {
                $progress($stats);
            }

            return $stats;
        } catch (Throwable $exception) {
            if (! $dryRun && $stats['run_id'] !== null) {
                DB::table('food_import_runs')
                    ->where('id', $stats['run_id'])
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
        array &$stats,
        bool $dryRun,
        ?callable $progress
    ): void {
        if (! $dryRun && $batch !== []) {
            DB::transaction(function () use (
                &$batch,
                $sourceId,
                &$stats
            ): void {
                $counts = $this->persist($batch, $sourceId);
                $stats['inserted'] += $counts['inserted'];
                $stats['updated'] += $counts['updated'];
                $this->updateRun($stats);
            });
            $batch = [];
        } elseif (! $dryRun) {
            $this->updateRun($stats);
        }

        if ($progress !== null) {
            $progress($stats);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @return array{inserted: int, updated: int}
     */
    private function persist(array $batch, int $sourceId): array
    {
        $batch = collect($batch)->keyBy('external_id')->values();
        $externalIds = $batch->pluck('external_id')->all();
        $existing = DB::table('foods')
            ->where('source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->all();
        $now = now();
        $rows = $batch->map(fn (array $item) => [
            ...$item['food'],
            'source_id' => $sourceId,
            'imported_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('foods')->upsert(
            $rows,
            ['source_id', 'external_id'],
            [
                'food_type',
                'search_priority',
                'name',
                'main_locale',
                'calories',
                'nutrition_basis_amount',
                'nutrition_basis_unit',
                'protein',
                'carbohydrates',
                'fat',
                'saturated_fat',
                'fibre',
                'sugar',
                'sodium',
                'salt',
                'nutrition_complete',
                'is_active',
                'search_text',
                'source_updated_at',
                'imported_at',
                'updated_at',
            ]
        );

        $foodIds = DB::table('foods')
            ->where('source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('id', 'external_id');
        $translations = $batch
            ->map(fn (array $item) => [
                'food_id' => $foodIds->get($item['external_id']),
                'locale' => 'en',
                'name' => $item['food']['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->filter(fn (array $row) => $row['food_id'] !== null)
            ->values()
            ->all();

        if ($translations !== []) {
            DB::table('food_translations')->upsert(
                $translations,
                ['food_id', 'locale'],
                ['name', 'updated_at']
            );
        }

        return [
            'inserted' => count($rows) - count($existing),
            'updated' => count($existing),
        ];
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
                'last_processed_line' => $stats['last_line'],
                'skip_reasons' => json_encode(
                    $stats['skip_reasons'],
                    JSON_THROW_ON_ERROR
                ),
                'updated_at' => now(),
                ...$extra,
            ]);
    }

    private function startRun(
        int $sourceId,
        string $path,
        string $dataset,
        bool $resume,
        bool $force
    ): object {
        $fingerprint = [
            'dataset' => $dataset,
            'file_size' => filesize($path) ?: 0,
            'file_modified_at' => filemtime($path) ?: 0,
        ];

        if ($resume) {
            $run = DB::table('food_import_runs')
                ->where('source_id', $sourceId)
                ->where('file_name', basename($path))
                ->whereIn('status', ['running', 'failed'])
                ->latest('id')
                ->first();

            if ($run === null) {
                throw new RuntimeException(
                    'No interrupted USDA import was found for this file.'
                );
            }

            $options = json_decode((string) $run->options, true);

            if (
                ! is_array($options)
                || ($options['dataset'] ?? null) !== $dataset
                || ($options['file_size'] ?? null)
                    !== $fingerprint['file_size']
            ) {
                throw new RuntimeException(
                    'The USDA archive does not match the interrupted import.'
                );
            }

            DB::table('food_import_runs')
                ->where('id', $run->id)
                ->update([
                    'status' => 'running',
                    'error_message' => null,
                    'finished_at' => null,
                    'updated_at' => now(),
                ]);

            return DB::table('food_import_runs')->find($run->id);
        }

        if (! $force) {
            $alreadyImported = DB::table('food_import_runs')
                ->where('source_id', $sourceId)
                ->where('file_name', basename($path))
                ->where('status', 'completed')
                ->latest('id')
                ->get()
                ->contains(function (object $run) use ($fingerprint): bool {
                    try {
                        $options = json_decode(
                            (string) $run->options,
                            true,
                            flags: JSON_THROW_ON_ERROR
                        );
                    } catch (JsonException) {
                        return false;
                    }

                    return ($options['dataset'] ?? null)
                            === $fingerprint['dataset']
                        && ($options['file_size'] ?? null)
                            === $fingerprint['file_size']
                        && ($options['file_modified_at'] ?? null)
                            === $fingerprint['file_modified_at'];
                });

            if ($alreadyImported) {
                throw new RuntimeException(
                    'This USDA archive was already imported. Use --force to run it again.'
                );
            }
        }

        $runId = DB::table('food_import_runs')->insertGetId([
            'source_id' => $sourceId,
            'status' => 'running',
            'file_name' => basename($path),
            'options' => json_encode($fingerprint, JSON_THROW_ON_ERROR),
            'started_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('food_import_runs')->find($runId);
    }

    private function validate(string $path, int $batchSize): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("USDA archive is not readable: {$path}");
        }

        if ($batchSize < 1 || $batchSize > 1000) {
            throw new RuntimeException('Batch size must be between 1 and 1000.');
        }
    }

    /**
     * @return array<string, int>
     */
    private function skipReasons(mixed $value): array
    {
        if (! is_string($value) || $value === '') {
            return [];
        }

        try {
            $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->filter(fn ($count, $reason) => is_string($reason)
                && is_numeric($count))
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
