<?php

namespace App\Console\Commands;

use App\Services\FoodCatalog\GenericFoodDeduplicator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class DeduplicateGenericFoods extends Command
{
    protected $signature = 'foods:deduplicate-generics
        {--dry-run : Report exact-name duplicates without linking them}';

    protected $description =
        'Link exact duplicate generic foods to one preferred source record';

    public function handle(GenericFoodDeduplicator $deduplicator): int
    {
        try {
            $lock = Cache::lock('deduplicate-generic-foods', 60 * 60);

            if (! $lock->get()) {
                throw new RuntimeException(
                    'Another generic food deduplication is already running.'
                );
            }

            try {
                $stats = $deduplicator->deduplicate(
                    (bool) $this->option('dry-run'),
                    fn (array $stats) => $this->line(sprintf(
                        'Scanned %s · groups %s · linked %s',
                        number_format($stats['foods_scanned']),
                        number_format($stats['duplicate_groups']),
                        number_format($stats['foods_linked'])
                    ))
                );
            } finally {
                $lock->release();
            }

            $message = $this->option('dry-run')
                ? 'Dry run finished. No foods were changed.'
                : 'Generic food deduplication finished.';
            $this->components->success($message);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
