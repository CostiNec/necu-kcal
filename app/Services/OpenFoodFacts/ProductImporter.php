<?php

namespace App\Services\OpenFoodFacts;

use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class ProductImporter
{
    public function __construct(
        private readonly GzipJsonlReader $reader,
        private readonly ProductMapper $mapper
    ) {}

    /**
     * @param  callable(array<string, int|string|null>): void|null  $progress
     * @return array{
     *     run_id: int|null,
     *     status: string,
     *     processed: int,
     *     eligible: int,
     *     inserted: int,
     *     updated: int,
     *     skipped: int,
     *     skip_reasons: array<string, int>,
     *     errors: int,
     *     last_line: int
     * }
     */
    public function import(
        string $path,
        string $scope = 'ro',
        int $batchSize = 500,
        ?int $limit = null,
        bool $dryRun = false,
        bool $resume = false,
        bool $force = false,
        ?callable $progress = null
    ): array {
        $this->validateOptions($path, $scope, $batchSize, $limit);

        $sourceId = DB::table('food_sources')
            ->where('code', config('open-food-facts.source_code'))
            ->value('id');

        if ($sourceId === null) {
            throw new RuntimeException(
                'Open Food Facts source is missing. Run the database migrations first.'
            );
        }

        $fingerprint = $this->fingerprint($path, $scope, $batchSize, $limit);
        $run = $dryRun
            ? null
            : $this->startRun(
                (int) $sourceId,
                $path,
                $fingerprint,
                $resume,
                $force
            );
        $stats = [
            'run_id' => $run?->id,
            'status' => $dryRun ? 'dry-run' : 'running',
            'processed' => (int) ($run?->processed_count ?? 0),
            'eligible' => 0,
            'inserted' => (int) ($run?->inserted_count ?? 0),
            'updated' => (int) ($run?->updated_count ?? 0),
            'skipped' => (int) ($run?->skipped_count ?? 0),
            'skip_reasons' => $this->decodeSkipReasons(
                $run?->skip_reasons ?? null
            ),
            'errors' => (int) ($run?->error_count ?? 0),
            'last_line' => (int) ($run?->last_processed_line ?? 0),
        ];
        $batch = [];
        $readThisRun = 0;
        $schemaVersion = $run?->source_schema_version;
        $lastExternalId = $run?->last_external_id;
        $progressEvery = max(
            1,
            (int) config('open-food-facts.progress_every', 10000)
        );
        $maxErrors = max(
            1,
            (int) config('open-food-facts.max_errors', 1000)
        );

        DB::disableQueryLog();

        try {
            foreach (
                $this->reader->read($path, $stats['last_line']) as $line
            ) {
                if ($limit !== null && $readThisRun >= $limit) {
                    break;
                }

                $readThisRun++;
                $stats['processed']++;
                $stats['last_line'] = $line['line'];

                try {
                    $product = json_decode(
                        $line['json'],
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );
                } catch (JsonException) {
                    $stats['errors']++;

                    if ($stats['errors'] > $maxErrors) {
                        throw new RuntimeException(
                            "Import stopped after more than {$maxErrors} invalid JSON records."
                        );
                    }

                    $this->checkpointIfDue(
                        $batch,
                        (int) $sourceId,
                        $stats,
                        $schemaVersion,
                        $lastExternalId,
                        $batchSize,
                        $progressEvery,
                        $dryRun,
                        $progress
                    );

                    continue;
                }

                if (! is_array($product)) {
                    $stats['errors']++;

                    if ($stats['errors'] > $maxErrors) {
                        throw new RuntimeException(
                            "Import stopped after more than {$maxErrors} invalid records."
                        );
                    }

                    $this->checkpointIfDue(
                        $batch,
                        (int) $sourceId,
                        $stats,
                        $schemaVersion,
                        $lastExternalId,
                        $batchSize,
                        $progressEvery,
                        $dryRun,
                        $progress
                    );

                    continue;
                }

                $mapping = $this->mapper->mapWithReason($product, $scope);
                $mapped = $mapping['product'];

                if ($mapped === null) {
                    $stats['skipped']++;
                    $reason = $mapping['skipped_reason'] ?? 'unknown';
                    $stats['skip_reasons'][$reason] =
                        ($stats['skip_reasons'][$reason] ?? 0) + 1;
                    $this->checkpointIfDue(
                        $batch,
                        (int) $sourceId,
                        $stats,
                        $schemaVersion,
                        $lastExternalId,
                        $batchSize,
                        $progressEvery,
                        $dryRun,
                        $progress
                    );

                    continue;
                }

                $stats['eligible']++;
                $lastExternalId = $mapped['external_id'];
                $schemaVersion = max(
                    (int) ($schemaVersion ?? 0),
                    (int) ($mapped['schema_version'] ?? 0)
                ) ?: null;

                if ($dryRun) {
                    $this->checkpointIfDue(
                        $batch,
                        (int) $sourceId,
                        $stats,
                        $schemaVersion,
                        $lastExternalId,
                        $batchSize,
                        $progressEvery,
                        true,
                        $progress
                    );

                    continue;
                }

                $batch[] = $mapped;

                $this->checkpointIfDue(
                    $batch,
                    (int) $sourceId,
                    $stats,
                    $schemaVersion,
                    $lastExternalId,
                    $batchSize,
                    $progressEvery,
                    false,
                    $progress
                );
            }

            if (! $dryRun && $batch !== []) {
                $this->checkpoint(
                    $batch,
                    (int) $sourceId,
                    $stats,
                    $schemaVersion,
                    $lastExternalId,
                    false,
                    $progress
                );
            }

            $stats['status'] = $limit === null
                ? ($dryRun ? 'dry-run' : 'completed')
                : ($dryRun ? 'dry-run' : 'completed_partial');

            if (! $dryRun) {
                DB::table('food_import_runs')
                    ->where('id', $stats['run_id'])
                    ->update([
                        'status' => $stats['status'],
                        'source_schema_version' => $schemaVersion,
                        'processed_count' => $stats['processed'],
                        'inserted_count' => $stats['inserted'],
                        'updated_count' => $stats['updated'],
                        'skipped_count' => $stats['skipped'],
                        'skip_reasons' => $this->encodeSkipReasons(
                            $stats['skip_reasons']
                        ),
                        'error_count' => $stats['errors'],
                        'last_processed_line' => $stats['last_line'],
                        'last_external_id' => $lastExternalId,
                        'finished_at' => now(),
                        'updated_at' => now(),
                    ]);
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
     * @param  array<string, int|string|null>  $stats
     */
    private function writeBatch(
        array $batch,
        int $sourceId,
        array &$stats,
        ?int $schemaVersion,
        ?string $lastExternalId
    ): void {
        DB::transaction(function () use (
            $batch,
            $sourceId,
            &$stats,
            $schemaVersion,
            $lastExternalId
        ): void {
            $counts = $this->persistBatch($batch, $sourceId);
            $stats['inserted'] += $counts['inserted'];
            $stats['updated'] += $counts['updated'];

            DB::table('food_import_runs')
                ->where('id', $stats['run_id'])
                ->update([
                    'source_schema_version' => $schemaVersion,
                    'processed_count' => $stats['processed'],
                    'inserted_count' => $stats['inserted'],
                    'updated_count' => $stats['updated'],
                    'skipped_count' => $stats['skipped'],
                    'skip_reasons' => $this->encodeSkipReasons(
                        $stats['skip_reasons']
                    ),
                    'error_count' => $stats['errors'],
                    'last_processed_line' => $stats['last_line'],
                    'last_external_id' => $lastExternalId,
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @return array{inserted: int, updated: int}
     */
    private function persistBatch(array $batch, int $sourceId): array
    {
        $batch = collect($batch)
            ->keyBy('external_id')
            ->values()
            ->all();
        $externalIds = collect($batch)
            ->pluck('external_id')
            ->unique()
            ->values();
        $existing = DB::table('foods')
            ->where('source_id', $sourceId)
            ->whereIn('external_id', $externalIds)
            ->pluck('external_id')
            ->all();
        $now = now();
        $foodRows = collect($batch)
            ->map(fn (array $product) => [
                ...$product['food'],
                'source_id' => $sourceId,
                'imported_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        DB::table('foods')->upsert(
            $foodRows,
            ['source_id', 'external_id'],
            [
                'food_type',
                'search_priority',
                'name',
                'brand',
                'main_locale',
                'barcode',
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
                'package_quantity',
                'package_unit',
                'is_public',
                'nutrition_complete',
                'is_active',
                'data_completeness',
                'popularity_score',
                'image_url',
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
        $managedFoodIds = $foodIds->values();

        DB::table('food_translations')
            ->whereIn('food_id', $managedFoodIds)
            ->whereIn('locale', ['ro', 'en'])
            ->delete();
        DB::table('food_markets')
            ->whereIn('food_id', $managedFoodIds)
            ->whereIn(
                'country_code',
                array_keys(config('open-food-facts.market_tags', []))
            )
            ->delete();
        DB::table('food_store')
            ->whereIn('food_id', $managedFoodIds)
            ->delete();

        $translationRows = [];
        $marketRows = [];
        $storeProducts = [];
        $storeRows = [];

        foreach ($batch as $product) {
            $foodId = $foodIds->get($product['external_id']);

            if ($foodId === null) {
                continue;
            }

            foreach ($product['translations'] as $locale => $name) {
                $translationRows[] = [
                    'food_id' => $foodId,
                    'locale' => $locale,
                    'name' => $name,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach ($product['markets'] as $countryCode) {
                $marketRows[] = [
                    'country_code' => $countryCode,
                    'food_id' => $foodId,
                ];
            }

            foreach ($product['stores'] as $store) {
                $storeRows[$store['external_key']] = [
                    'source_id' => $sourceId,
                    'external_key' => $store['external_key'],
                    'name' => $store['name'],
                ];
                $storeProducts[] = [
                    'external_key' => $store['external_key'],
                    'food_id' => $foodId,
                ];
            }
        }

        if ($translationRows !== []) {
            DB::table('food_translations')->insert($translationRows);
        }

        if ($marketRows !== []) {
            DB::table('food_markets')->insertOrIgnore($marketRows);
        }

        if ($storeRows !== []) {
            DB::table('stores')->upsert(
                array_values($storeRows),
                ['source_id', 'external_key'],
                ['name']
            );
            $storeIds = DB::table('stores')
                ->where('source_id', $sourceId)
                ->whereIn('external_key', array_keys($storeRows))
                ->pluck('id', 'external_key');
            $pivotRows = collect($storeProducts)
                ->map(fn (array $storeProduct) => [
                    'store_id' => $storeIds->get(
                        $storeProduct['external_key']
                    ),
                    'food_id' => $storeProduct['food_id'],
                ])
                ->filter(fn (array $row) => $row['store_id'] !== null)
                ->unique(
                    fn (array $row) => "{$row['store_id']}:{$row['food_id']}"
                )
                ->values()
                ->all();

            if ($pivotRows !== []) {
                DB::table('food_store')->insertOrIgnore($pivotRows);
            }
        }

        $updated = count($existing);

        return [
            'inserted' => count($foodRows) - $updated,
            'updated' => $updated,
        ];
    }

    /**
     * @param  array<string, int|string|null>  $stats
     */
    private function checkpointIfDue(
        array &$batch,
        int $sourceId,
        array &$stats,
        ?int $schemaVersion,
        ?string $lastExternalId,
        int $batchSize,
        int $progressEvery,
        bool $dryRun,
        ?callable $progress
    ): void {
        if (
            count($batch) < $batchSize
            && $stats['processed'] % $progressEvery !== 0
        ) {
            return;
        }

        $this->checkpoint(
            $batch,
            $sourceId,
            $stats,
            $schemaVersion,
            $lastExternalId,
            $dryRun,
            $progress
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @param  array<string, int|string|null>  $stats
     */
    private function checkpoint(
        array &$batch,
        int $sourceId,
        array &$stats,
        ?int $schemaVersion,
        ?string $lastExternalId,
        bool $dryRun,
        ?callable $progress
    ): void {
        if (! $dryRun && $batch !== []) {
            $this->writeBatch(
                $batch,
                $sourceId,
                $stats,
                $schemaVersion,
                $lastExternalId
            );
            $batch = [];
        } elseif (! $dryRun) {
            DB::table('food_import_runs')
                ->where('id', $stats['run_id'])
                ->update([
                    'source_schema_version' => $schemaVersion,
                    'processed_count' => $stats['processed'],
                    'inserted_count' => $stats['inserted'],
                    'updated_count' => $stats['updated'],
                    'skipped_count' => $stats['skipped'],
                    'skip_reasons' => $this->encodeSkipReasons(
                        $stats['skip_reasons']
                    ),
                    'error_count' => $stats['errors'],
                    'last_processed_line' => $stats['last_line'],
                    'last_external_id' => $lastExternalId,
                    'updated_at' => now(),
                ]);
        }

        if ($progress !== null) {
            $progress($stats);
        }
    }

    /**
     * @param  array<string, mixed>  $fingerprint
     */
    private function startRun(
        int $sourceId,
        string $path,
        array $fingerprint,
        bool $resume,
        bool $force
    ): object {
        if ($resume) {
            $run = DB::table('food_import_runs')
                ->where('source_id', $sourceId)
                ->where('file_name', basename($path))
                ->whereIn('status', ['running', 'failed'])
                ->latest('id')
                ->first();

            if ($run === null) {
                throw new RuntimeException(
                    'No interrupted import run was found for this file.'
                );
            }

            $previousOptions = json_decode(
                (string) $run->options,
                true
            );

            if (
                ! is_array($previousOptions)
                || ($previousOptions['scope'] ?? null) !== $fingerprint['scope']
                || ($previousOptions['file_size'] ?? null)
                    !== $fingerprint['file_size']
            ) {
                throw new RuntimeException(
                    'The dump or scope does not match the interrupted import.'
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

        if (! $force && $fingerprint['limit'] === null) {
            $alreadyImported = DB::table('food_import_runs')
                ->where('source_id', $sourceId)
                ->where('file_name', basename($path))
                ->where('status', 'completed')
                ->latest('id')
                ->get()
                ->contains(function (object $run) use ($fingerprint): bool {
                    $options = json_decode((string) $run->options, true);

                    return is_array($options)
                        && ($options['scope'] ?? null) === $fingerprint['scope']
                        && ($options['file_size'] ?? null)
                            === $fingerprint['file_size']
                        && ($options['file_modified_at'] ?? null)
                            === $fingerprint['file_modified_at'];
                });

            if ($alreadyImported) {
                throw new RuntimeException(
                    'This dump and scope were already imported. Use --force to run it again.'
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

    /**
     * @return array<string, int|string|null>
     */
    private function fingerprint(
        string $path,
        string $scope,
        int $batchSize,
        ?int $limit
    ): array {
        return [
            'scope' => $scope,
            'batch_size' => $batchSize,
            'limit' => $limit,
            'file_size' => filesize($path) ?: 0,
            'file_modified_at' => filemtime($path) ?: 0,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function decodeSkipReasons(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (! is_array($value)) {
            return [];
        }

        $reasons = [];

        foreach ($value as $reason => $count) {
            if (is_string($reason) && is_numeric($count) && $count >= 0) {
                $reasons[$reason] = (int) $count;
            }
        }

        return $reasons;
    }

    /**
     * @param  array<string, int>  $reasons
     */
    private function encodeSkipReasons(array $reasons): string
    {
        arsort($reasons);

        return json_encode($reasons, JSON_THROW_ON_ERROR);
    }

    private function validateOptions(
        string $path,
        string $scope,
        int $batchSize,
        ?int $limit
    ): void {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException("Dump file is not readable: {$path}");
        }

        if (! in_array($scope, ['ro', 'all'], true)) {
            throw new RuntimeException('Scope must be either "ro" or "all".');
        }

        if ($batchSize < 1 || $batchSize > 1000) {
            throw new RuntimeException('Batch size must be between 1 and 1000.');
        }

        if ($limit !== null && $limit < 1) {
            throw new RuntimeException('Limit must be at least 1.');
        }
    }
}
