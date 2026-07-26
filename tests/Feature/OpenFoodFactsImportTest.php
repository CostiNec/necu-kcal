<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class OpenFoodFactsImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_romanian_products_and_related_catalog_data(): void
    {
        $path = $this->gzipDump([
            $this->romanianProduct(),
            [
                ...$this->romanianProduct(),
                'code' => '2222222222222',
                'product_name' => 'French product',
                'countries_tags' => ['en:france'],
            ],
            [
                ...$this->romanianProduct(),
                'code' => '3333333333333',
                'product_name' => 'No calories',
                'nutriments' => ['proteins_100g' => 4],
            ],
        ]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'ro',
            '--batch' => 2,
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $sourceId = DB::table('food_sources')
            ->where('code', 'open_food_facts')
            ->value('id');
        $food = DB::table('foods')
            ->where('source_id', $sourceId)
            ->where('external_id', '1111111111111')
            ->first();

        $this->assertNotNull($food);
        $this->assertSame('Piept de pui gata preparat', $food->name);
        $this->assertSame('Test Brand', $food->brand);
        $this->assertEquals(165, $food->calories);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(31, $food->protein);
        $this->assertEquals(2.4, $food->fibre);
        $this->assertEquals(250, $food->package_quantity);
        $this->assertSame('g', $food->package_unit);
        $this->assertSame('ro', $food->main_locale);
        $this->assertStringContainsString(
            'Chicken breast',
            $food->search_text
        );

        $this->assertDatabaseHas('food_translations', [
            'food_id' => $food->id,
            'locale' => 'ro',
            'name' => 'Piept de pui gata preparat',
        ]);
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $food->id,
            'locale' => 'en',
            'name' => 'Chicken breast',
        ]);
        $this->assertDatabaseHas('food_markets', [
            'food_id' => $food->id,
            'country_code' => 'RO',
        ]);
        $this->assertDatabaseHas('stores', [
            'source_id' => $sourceId,
            'external_key' => 'lidl',
            'name' => 'Lidl',
        ]);

        $storeId = DB::table('stores')
            ->where('source_id', $sourceId)
            ->where('external_key', 'lidl')
            ->value('id');

        $this->assertDatabaseHas('food_store', [
            'food_id' => $food->id,
            'store_id' => $storeId,
        ]);
        $this->assertDatabaseHas('food_import_runs', [
            'source_id' => $sourceId,
            'status' => 'completed',
            'processed_count' => 3,
            'inserted_count' => 1,
            'updated_count' => 0,
            'skipped_count' => 2,
            'error_count' => 0,
            'last_processed_line' => 3,
        ]);
        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame([
            'outside_scope' => 1,
            'missing_energy' => 1,
        ], json_decode($run->skip_reasons, true));
    }

    public function test_it_reports_and_persists_each_skip_reason(): void
    {
        $missingBarcode = $this->romanianProduct();
        unset($missingBarcode['code']);
        $missingBarcode['_id'] = 'off-record-without-barcode';

        $missingSourceId = $this->romanianProduct();
        unset($missingSourceId['code']);

        $missingName = $this->romanianProduct();
        unset(
            $missingName['product_name'],
            $missingName['product_name_ro'],
            $missingName['product_name_en']
        );
        $missingName['code'] = '2222222222222';

        $missingEnergy = [
            ...$this->romanianProduct(),
            'code' => '3333333333333',
            'nutriments' => [],
        ];
        $liquid = [
            ...$this->romanianProduct(),
            'code' => '4444444444444',
            'product_quantity' => 500,
            'product_quantity_unit' => 'ml',
        ];
        $outsideScope = [
            ...$this->romanianProduct(),
            'code' => '5555555555555',
            'countries_tags' => ['en:france'],
        ];
        $path = $this->gzipDump([
            $this->romanianProduct(),
            $missingBarcode,
            $missingSourceId,
            $missingName,
            $missingEnergy,
            $liquid,
            $outsideScope,
        ]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'ro',
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $status, $output);
        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame(7, $run->processed_count);
        $this->assertSame(4, $run->skipped_count);
        $this->assertSame([
            'missing_source_id' => 1,
            'missing_name' => 1,
            'missing_energy' => 1,
            'outside_scope' => 1,
        ], json_decode($run->skip_reasons, true));
        $this->assertDatabaseHas('foods', [
            'external_id' => 'off-record-without-barcode',
            'barcode' => null,
        ]);
        $this->assertDatabaseHas('foods', [
            'external_id' => '4444444444444',
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'ml',
            'package_quantity' => 500,
            'package_unit' => 'ml',
        ]);
        $this->assertStringContainsString(
            'missing/invalid energy',
            $output
        );
    }

    public function test_dry_run_maps_records_without_writing_any_data(): void
    {
        $path = $this->gzipDump([$this->romanianProduct()]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
            '--limit' => 1,
            '--dry-run' => true,
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $this->assertDatabaseCount('foods', 0);
        $this->assertDatabaseCount('food_import_runs', 0);
    }

    public function test_force_reimport_updates_a_product_instead_of_duplicating_it(): void
    {
        $product = $this->romanianProduct();
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $product['product_name_ro'] = 'Piept de pui actualizat';
        $product['product_name'] = 'Piept de pui actualizat';
        $product['nutriments']['energy-kcal_100g'] = 172;
        $this->writeGzipDump($path, [$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--force' => true,
        ]);
        $this->assertSame(0, $status, Artisan::output());

        $this->assertDatabaseCount('foods', 1);
        $this->assertDatabaseHas('foods', [
            'external_id' => '1111111111111',
            'name' => 'Piept de pui actualizat',
            'calories' => 172,
        ]);
        $this->assertDatabaseHas('food_import_runs', [
            'status' => 'completed',
            'inserted_count' => 0,
            'updated_count' => 1,
        ]);
    }

    public function test_it_decodes_html_entities_in_imported_text(): void
    {
        $product = $this->romanianProduct();
        $product['product_name'] = '&quot;CRISPY&quot; &amp; spicy';
        $product['product_name_ro'] = '&amp;quot;CRISPY&amp;quot; picant';
        $product['brands'] = 'Brand &amp; Co';
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')->first();
        $this->assertSame('"CRISPY" & spicy', $food->name);
        $this->assertSame('Brand & Co', $food->brand);
        $this->assertDatabaseHas('food_translations', [
            'food_id' => $food->id,
            'locale' => 'ro',
            'name' => '"CRISPY" picant',
        ]);
    }

    public function test_sparse_products_are_flushed_before_the_resume_checkpoint(): void
    {
        config([
            'open-food-facts.progress_every' => 2,
            'open-food-facts.max_errors' => 1,
        ]);
        $path = $this->gzipDump([
            $this->romanianProduct(),
            [
                ...$this->romanianProduct(),
                'code' => '2222222222222',
                'countries_tags' => ['en:france'],
            ],
            '{invalid json',
            '{invalid json again',
        ]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'ro',
            '--batch' => 500,
        ]);

        $this->assertSame(1, $status);
        $this->assertDatabaseHas('foods', [
            'external_id' => '1111111111111',
        ]);
        $this->assertDatabaseHas('food_import_runs', [
            'status' => 'failed',
            'processed_count' => 2,
            'inserted_count' => 1,
            'skipped_count' => 1,
            'last_processed_line' => 2,
        ]);
        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame(
            ['outside_scope' => 1],
            json_decode($run->skip_reasons, true)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function romanianProduct(): array
    {
        return [
            'code' => '1111111111111',
            'product_name' => 'Piept de pui gata preparat',
            'product_name_ro' => 'Piept de pui gata preparat',
            'product_name_en' => 'Chicken breast',
            'brands' => 'Test Brand',
            'lang' => 'ro',
            'countries_tags' => ['en:romania'],
            'stores_tags' => ['lidl'],
            'nutriments' => [
                'energy-kcal_100g' => 165,
                'proteins_100g' => 31,
                'carbohydrates_100g' => 0,
                'fat_100g' => 3.6,
                'saturated-fat_100g' => 1,
                'fiber_100g' => 2.4,
                'sugars_100g' => 0,
                'sodium_100g' => 0.074,
                'salt_100g' => 0.19,
            ],
            'serving_quantity' => 125,
            'serving_quantity_unit' => 'g',
            'product_quantity' => 0.25,
            'product_quantity_unit' => 'kg',
            'completeness' => 0.92,
            'unique_scans_n' => 124,
            'image_front_small_url' => 'https://images.example/food.jpg',
            'last_modified_t' => 1785091200,
            'schema_version' => 1002,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $records
     */
    private function gzipDump(array $records): string
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

        $path = $directory.'/off-'.Str::uuid().'.jsonl.gz';
        $this->writeGzipDump($path, $records);
        $this->beforeApplicationDestroyed(
            static fn () => is_file($path) && unlink($path)
        );

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>|string>  $records
     */
    private function writeGzipDump(string $path, array $records): void
    {
        $handle = gzopen($path, 'wb9');

        if ($handle === false) {
            throw new RuntimeException(
                "Unable to create test dump: {$path}"
            );
        }

        try {
            foreach ($records as $record) {
                $json = is_string($record)
                    ? $record
                    : json_encode($record, JSON_THROW_ON_ERROR);
                gzwrite($handle, $json.PHP_EOL);
            }
        } finally {
            gzclose($handle);
        }
    }
}
