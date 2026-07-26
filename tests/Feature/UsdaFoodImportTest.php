<?php

namespace Tests\Feature;

use App\Services\UsdaFoodData\ArchiveDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class UsdaFoodImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_generic_foods_and_audits_skipped_records(): void
    {
        $path = $this->zipDataset([
            $this->tomato(),
            [
                'fdcId' => 2002,
                'description' => 'Avocado, raw',
                'publicationDate' => '2026-04-30',
                'foodNutrients' => [
                    $this->nutrient(1062, 669),
                    $this->nutrient(1003, 2),
                    $this->nutrient(1004, 14.66),
                    $this->nutrient(1005, 8.53),
                    $this->nutrient(1079, 6.7),
                ],
            ],
            [
                'fdcId' => 2003,
                'description' => 'Food without energy',
                'foodNutrients' => [
                    $this->nutrient(1003, 2),
                ],
            ],
            [
                'description' => 'Food without an FDC identifier',
                'foodNutrients' => [
                    $this->nutrient(1008, 42),
                ],
            ],
        ]);
        config([
            'usda-food-data.datasets.foundation.path' => $path,
            'usda-food-data.progress_every' => 2,
        ]);

        $status = Artisan::call('foods:import-usda', [
            '--dataset' => 'foundation',
            '--batch' => 1,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $sourceId = DB::table('food_sources')
            ->where('code', 'usda_food_data_central')
            ->value('id');
        $tomato = DB::table('foods')
            ->where('source_id', $sourceId)
            ->where('external_id', '2001')
            ->first();

        $this->assertNotNull($tomato);
        $this->assertSame(
            'Tomato, red {ripe}, raw "market sample"',
            $tomato->name
        );
        $this->assertSame('generic', $tomato->food_type);
        $this->assertNull($tomato->brand);
        $this->assertNull($tomato->barcode);
        $this->assertSame('en', $tomato->main_locale);
        $this->assertEquals(100, $tomato->nutrition_basis_amount);
        $this->assertSame('g', $tomato->nutrition_basis_unit);
        $this->assertEquals(18, $tomato->calories);
        $this->assertEquals(0.88, $tomato->protein);
        $this->assertEquals(3.89, $tomato->carbohydrates);
        $this->assertEquals(0.2, $tomato->fat);
        $this->assertEquals(1.2, $tomato->fibre);
        $this->assertEquals(0.005, $tomato->sodium);
        $this->assertEquals(0.013, $tomato->salt);
        $this->assertStringContainsString(
            'Vegetables and Vegetable Products',
            $tomato->search_text
        );
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $tomato->id,
            'locale' => 'en',
            'name' => 'Tomato, red {ripe}, raw "market sample"',
        ]);

        $avocado = DB::table('foods')
            ->where('source_id', $sourceId)
            ->where('external_id', '2002')
            ->first();
        $this->assertNotNull($avocado);
        $this->assertEquals(159.89, $avocado->calories);

        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame('completed', $run->status);
        $this->assertSame(4, $run->processed_count);
        $this->assertSame(2, $run->inserted_count);
        $this->assertSame(0, $run->updated_count);
        $this->assertSame(2, $run->skipped_count);
        $this->assertSame([
            'missing_energy' => 1,
            'missing_source_id' => 1,
        ], json_decode($run->skip_reasons, true));
    }

    public function test_force_reimport_updates_food_without_duplicating_it(): void
    {
        $food = $this->tomato();
        $path = $this->zipDataset([$food]);
        config([
            'usda-food-data.datasets.foundation.path' => $path,
        ]);

        $status = Artisan::call('foods:import-usda', [
            '--dataset' => 'foundation',
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $food['description'] = 'Tomato, updated';
        $food['foodNutrients'][0]['amount'] = 21;
        $this->writeZipDataset($path, [$food]);

        $status = Artisan::call('foods:import-usda', [
            '--dataset' => 'foundation',
            '--force' => true,
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $this->assertDatabaseCount('foods', 1);
        $this->assertDatabaseHas('foods', [
            'external_id' => '2001',
            'name' => 'Tomato, updated',
            'calories' => 21,
        ]);
        $this->assertDatabaseHas('food_import_runs', [
            'status' => 'completed',
            'inserted_count' => 0,
            'updated_count' => 1,
        ]);
    }

    public function test_dry_run_streams_records_without_writing_them(): void
    {
        $path = $this->zipDataset([$this->tomato()]);
        config([
            'usda-food-data.datasets.foundation.path' => $path,
        ]);

        $status = Artisan::call('foods:import-usda', [
            '--dataset' => 'foundation',
            '--dry-run' => true,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseCount('foods', 0);
        $this->assertDatabaseCount('food_import_runs', 0);
    }

    public function test_import_downloads_a_missing_archive_before_reading_it(): void
    {
        $source = $this->zipDataset([$this->tomato()]);
        $target = storage_path(
            'framework/testing/usda-download-'.Str::uuid().'.zip'
        );
        config([
            'usda-food-data.datasets.foundation.path' => $target,
        ]);
        $this->beforeApplicationDestroyed(
            static fn () => is_file($target) && unlink($target)
        );

        $downloader = Mockery::mock(ArchiveDownloader::class);
        $downloader
            ->shouldReceive('ensureAvailable')
            ->once()
            ->with(
                'foundation',
                Mockery::on(
                    fn (array $settings) => $settings['path'] === $target
                ),
                false,
                Mockery::type('callable')
            )
            ->andReturnUsing(
                function (
                    string $dataset,
                    array $settings
                ) use ($source): string {
                    copy($source, $settings['path']);

                    return $settings['path'];
                }
            );
        $this->app->instance(ArchiveDownloader::class, $downloader);

        $status = Artisan::call('foods:import-usda', [
            '--dataset' => 'foundation',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertFileExists($target);
        $this->assertDatabaseHas('foods', [
            'external_id' => '2001',
            'food_type' => 'generic',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tomato(): array
    {
        return [
            'fdcId' => 2001,
            'description' => 'Tomato, red {ripe}, raw "market sample"',
            'scientificName' => 'Solanum lycopersicum',
            'publicationDate' => '2026-04-30',
            'foodCategory' => [
                'description' => 'Vegetables and Vegetable Products',
            ],
            'foodNutrients' => [
                $this->nutrient(1008, 18),
                $this->nutrient(1003, 0.88),
                $this->nutrient(1004, 0.2),
                $this->nutrient(1005, 3.89),
                $this->nutrient(1079, 1.2),
                $this->nutrient(1093, 5),
                $this->nutrient(2000, 2.63),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function nutrient(int $id, float|int $amount): array
    {
        return [
            'amount' => $amount,
            'nutrient' => ['id' => $id],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $foods
     */
    private function zipDataset(array $foods): string
    {
        $directory = storage_path('framework/testing');

        if (
            ! is_dir($directory)
            && ! mkdir($directory, 0777, true)
            && ! is_dir($directory)
        ) {
            throw new RuntimeException(
                "Unable to create test directory: {$directory}"
            );
        }

        $path = $directory.'/usda-'.Str::uuid().'.zip';
        $this->writeZipDataset($path, $foods);
        $this->beforeApplicationDestroyed(
            static fn () => is_file($path) && unlink($path)
        );

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $foods
     */
    private function writeZipDataset(string $path, array $foods): void
    {
        $archive = new ZipArchive;

        if ($archive->open(
            $path,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        ) !== true) {
            throw new RuntimeException(
                "Unable to create USDA test archive: {$path}"
            );
        }

        try {
            $archive->addFromString(
                'FoodData_Central_foundation_food_json.json',
                json_encode(
                    ['FoundationFoods' => $foods],
                    JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT
                )
            );
        } finally {
            $archive->close();
        }
    }
}
