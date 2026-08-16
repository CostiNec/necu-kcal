<?php

namespace App\Console\Commands;

use App\Models\Food;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class RebuildFoodSearchIndex extends Command
{
    protected $signature = 'foods:rebuild-search-index
        {--chunk=500 : Number of food IDs handled by each import job}
        {--sync : Import inline instead of dispatching queued range jobs}';

    protected $description =
        'Flush and rebuild the active canonical food collection in Typesense';

    public function handle(): int
    {
        try {
            $this->ensureTypesenseIsConfigured();
            $chunk = max(1, (int) $this->option('chunk'));
            $lock = Cache::lock('rebuild-food-search-index', 60 * 60 * 24);

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another food search index rebuild is already running.'
                );
            }

            try {
                $this->components->info('Flushing the Typesense food collection.');
                Food::removeAllFromSearch();

                if ($this->option('sync')) {
                    config(['scout.queue' => false]);
                    $this->components->info(
                        'Importing active canonical foods inline.'
                    );
                    Food::makeAllSearchable($chunk);
                    $this->components->success(
                        'The Typesense food collection has been rebuilt.'
                    );

                    return self::SUCCESS;
                }

                $this->components->info(
                    'Dispatching queued ID ranges for the food import.'
                );
                $status = Artisan::call(
                    'scout:queue-import',
                    [
                        'model' => Food::class,
                        '--chunk' => $chunk,
                    ],
                    $this->output
                );

                if ($status !== self::SUCCESS) {
                    throw new RuntimeException(
                        'Scout could not queue the food index import.'
                    );
                }
            } finally {
                $lock->release();
            }

            $this->components->success(
                'Food indexing jobs were queued. Keep the queue worker running.'
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function ensureTypesenseIsConfigured(): void
    {
        if (config('scout.driver') !== 'typesense') {
            throw new RuntimeException(
                'Set SCOUT_DRIVER to typesense before rebuilding the index.'
            );
        }

        if (blank(config('scout.typesense.client-settings.api_key'))) {
            throw new RuntimeException('TYPESENSE_API_KEY is required.');
        }
    }
}
