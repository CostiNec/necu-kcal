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

    public function test_it_imports_the_aggregated_nutrition_schema(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '2222222222222',
            'product_name' => 'Hazelnut chocolate spread',
            'product_quantity' => 350,
            'product_quantity_unit' => 'g',
            'nutriments' => [],
            'nutrition' => [
                'aggregated_set' => [
                    'per' => '100g',
                    'preparation' => 'as_sold',
                    'nutrients' => [
                        'energy-kcal' => [
                            'value' => 617,
                            'value_computed' => 608,
                            'unit' => 'kcal',
                        ],
                        'proteins' => ['value' => 8, 'unit' => 'g'],
                        'carbohydrates' => ['value' => 36, 'unit' => 'g'],
                        'fat' => ['value' => 48, 'unit' => 'g'],
                        'saturated-fat' => ['value' => 10, 'unit' => 'g'],
                        'sugars' => ['value' => 32, 'unit' => 'g'],
                        'sodium' => ['value' => 40, 'unit' => 'mg'],
                        'salt' => ['value' => 0.01, 'unit' => 'g'],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '2222222222222')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(617, $food->calories);
        $this->assertEquals(8, $food->protein);
        $this->assertEquals(36, $food->carbohydrates);
        $this->assertEquals(48, $food->fat);
        $this->assertEquals(10, $food->saturated_fat);
        $this->assertNull($food->fibre);
        $this->assertEquals(32, $food->sugar);
        $this->assertEquals(0.04, $food->sodium);
        $this->assertEquals(0.01, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(350, $food->package_quantity);
        $this->assertSame('g', $food->package_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_converts_aggregated_kilojoules_per_100ml(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '3333333333333',
            'product_name' => 'Test drink',
            'product_quantity' => 500,
            'product_quantity_unit' => 'ml',
            'nutriments' => [],
            'nutrition' => [
                'aggregated_set' => [
                    'per' => '100 ml',
                    'nutrients' => [
                        'energy-kj' => [
                            'value_computed' => 418.4,
                            'unit' => 'kJ',
                        ],
                        'proteins' => ['value' => 1, 'unit' => 'g'],
                        'carbohydrates' => ['value' => 20, 'unit' => 'g'],
                        'fat' => ['value' => 0, 'unit' => 'g'],
                        'fiber' => ['value' => 250, 'unit' => 'mg'],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseHas('foods', [
            'external_id' => '3333333333333',
            'calories' => 100,
            'nutrition_basis_amount' => 100,
            'nutrition_basis_unit' => 'ml',
            'fibre' => 0.25,
        ]);
    }

    public function test_it_merges_as_sold_input_sets_and_normalizes_servings(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0013562300600',
            'product_name' => 'Yummy Bunnies & Cheddar',
            'product_quantity' => 71,
            'product_quantity_unit' => 'g',
            'nutriments' => [],
            'nutrition' => [
                'aggregated_set' => [
                    'preparation' => 'prepared',
                    'per' => '100g',
                    'nutrients' => [
                        'energy-kcal' => [
                            'value' => 380.28,
                            'unit' => 'kcal',
                        ],
                        'carbohydrates' => [
                            'value' => 69.01,
                            'unit' => 'g',
                        ],
                    ],
                ],
                'input_sets' => [
                    [
                        'preparation' => 'prepared',
                        'source' => 'packaging',
                        'per' => 'serving',
                        'per_quantity' => 71,
                        'per_unit' => 'g',
                        'nutrients' => [
                            'carbohydrates' => [
                                'value' => 49,
                                'unit' => 'g',
                            ],
                        ],
                    ],
                    [
                        'preparation' => 'as_sold',
                        'source' => 'packaging',
                        'per' => '100g',
                        'per_quantity' => 100,
                        'per_unit' => 'g',
                        'nutrients' => [
                            'energy-kcal' => [
                                'value' => 380.28169014085,
                                'unit' => 'kcal',
                            ],
                        ],
                    ],
                    [
                        'preparation' => 'as_sold',
                        'source' => 'packaging',
                        'per' => 'serving',
                        'per_quantity' => 71,
                        'per_unit' => 'g',
                        'nutrients' => [
                            'energy-kcal' => [
                                'value' => 270,
                                'unit' => 'kcal',
                            ],
                            'proteins' => ['value' => 10, 'unit' => 'g'],
                            'carbohydrates' => [
                                'value' => 48,
                                'unit' => 'g',
                            ],
                            'fat' => ['value' => 4, 'unit' => 'g'],
                            'fiber' => ['value' => 3, 'unit' => 'g'],
                            'sugars' => ['value' => 4, 'unit' => 'g'],
                            'saturated-fat' => [
                                'value' => 2,
                                'unit' => 'g',
                            ],
                            'sodium' => ['value' => 0.39, 'unit' => 'g'],
                            'salt' => ['value' => 0.975, 'unit' => 'g'],
                        ],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0013562300600')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(380.28, $food->calories);
        $this->assertEquals(14.08, $food->protein);
        $this->assertEquals(67.61, $food->carbohydrates);
        $this->assertEquals(5.63, $food->fat);
        $this->assertEquals(4.23, $food->fibre);
        $this->assertEquals(5.63, $food->sugar);
        $this->assertEquals(2.82, $food->saturated_fat);
        $this->assertEquals(0.55, $food->sodium);
        $this->assertEquals(1.37, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(71, $food->package_quantity);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_legacy_nutriments_take_priority_over_aggregated_values(): void
    {
        $product = $this->romanianProduct();
        $product['nutrition'] = [
            'aggregated_set' => [
                'per' => '100g',
                'nutrients' => [
                    'energy-kcal' => ['value' => 999, 'unit' => 'kcal'],
                    'proteins' => ['value' => 99, 'unit' => 'g'],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseHas('foods', [
            'external_id' => '1111111111111',
            'calories' => 165,
            'protein' => 31,
        ]);
    }

    public function test_it_rejects_aggregated_nutrition_without_a_100_unit_basis(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'nutriments' => [],
            'nutrition' => [
                'aggregated_set' => [
                    'per' => 'serving',
                    'nutrients' => [
                        'energy-kcal' => [
                            'value' => 200,
                            'unit' => 'kcal',
                        ],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseCount('foods', 0);
        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame(
            ['missing_energy' => 1],
            json_decode($run->skip_reasons, true)
        );
    }

    public function test_it_imports_unsuffixed_nutrients_with_an_imported_100g_basis(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0000105000011',
            'product_name' => 'Chamomile Herbal Tea',
            'product_quantity' => 1,
            'product_quantity_unit' => 'g',
            'serving_quantity' => 1,
            'serving_quantity_unit' => 'g',
            'nutrition_data_per' => 'serving',
            'nutrition_data_per_imported' => '100g',
            'nutriments' => [
                'energy-kcal' => 280,
                'energy' => 1172,
                'proteins' => 0,
                'carbohydrates' => 70,
                'fat' => 0,
                'sodium' => 0.3,
                'salt' => 0.75,
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0000105000011')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(280, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(70, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertNull($food->fibre);
        $this->assertEquals(0.3, $food->sodium);
        $this->assertEquals(0.75, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(1, $food->package_quantity);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_treats_legacy_usda_unsuffixed_nutrients_as_per_100g(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0000105000059',
            'product_name' => 'Linden Flowers Tea',
            'creator' => 'usda-ndb-import',
            'data_sources_tags' => ['database-usda-ndb', 'databases'],
            'product_quantity' => 1.5,
            'product_quantity_unit' => 'g',
            'serving_quantity' => 1.5,
            'serving_quantity_unit' => 'g',
            'nutrition_data_per' => 'serving',
            'nutriments' => [
                'energy-kcal' => 213.32,
                'energy' => 893,
                'proteins' => 0,
                'carbohydrates' => 53.33,
                'fat' => 0,
                'sodium' => 0,
                'salt' => 0,
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0000105000059')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(213.32, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(53.33, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertNull($food->fibre);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(1.5, $food->package_quantity);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_imports_plain_water_as_zero_calorie_nutrition(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0012142040370',
            'product_name' => 'Purified drinking water',
            'product_quantity' => null,
            'product_quantity_unit' => null,
            'nutrition_data_per' => '100g',
            'nutriments' => [
                'nutrition-score-fr_100g' => 0,
                'nova-group_100g' => 1,
            ],
            'ingredients_tags' => ['en:water'],
            'categories_tags' => [
                'en:beverages',
                'en:waters',
                'en:spring-waters',
            ],
            'nutriscore_data' => ['is_water' => '1'],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0012142040370')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(0, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(0, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0, $food->sugar);
        $this->assertEquals(0, $food->sodium);
        $this->assertEquals(0, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_imports_carbonated_water_without_an_energy_field(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0021136010626',
            'product_name' => 'Topo Chico Sparkling Mineral Water',
            'product_quantity' => null,
            'product_quantity_unit' => null,
            'nutrition_data_per' => '100g',
            'nutrition_data_per_imported' => '100g',
            'nutriments' => [
                'proteins_100g' => 0,
                'carbohydrates_100g' => 0,
                'fat_100g' => 0,
                'sodium_100g' => 0.006,
                'salt_100g' => 0.015,
            ],
            'ingredients_tags' => [
                'en:mineral-water',
                'en:water',
                'en:e290',
            ],
            'categories_tags' => [
                'en:beverages',
                'en:waters',
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0021136010626')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(0, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(0, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0.01, $food->sodium);
        $this->assertEquals(0.02, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_imports_natural_mineral_water_taxonomy_aliases(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0041508963985',
            'product_name' => 'Carbonated Natural Mineral Water',
            'product_quantity' => 1000,
            'product_quantity_unit' => 'ml',
            'serving_quantity' => 375,
            'serving_quantity_unit' => 'ml',
            'nutriments' => [],
            'ingredients_tags' => [
                'en:natural-mineral-water',
                'en:water',
                'en:mineral-water',
                'en:e290',
            ],
            'categories_tags' => [
                'en:beverages',
                'en:waters',
                'en:mineral-waters',
                'en:carbonated-waters',
            ],
            'nutrition' => [
                'aggregated_set' => [
                    'preparation' => 'as_sold',
                    'per' => '100ml',
                    'nutrients' => [
                        'sodium' => [
                            'value' => 0.0032,
                            'unit' => 'g',
                        ],
                        'salt' => [
                            'value' => 0.008,
                            'unit' => 'g',
                        ],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0041508963985')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(0, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(0, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0, $food->sugar);
        $this->assertEquals(0, $food->sodium);
        $this->assertEquals(0.01, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('ml', $food->nutrition_basis_unit);
        $this->assertEquals(1000, $food->package_quantity);
        $this->assertSame('ml', $food->package_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_imports_single_ingredient_zero_macro_tea(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0047900313502',
            'product_name' => 'Decaffeinated Tea',
            'product_quantity' => null,
            'product_quantity_unit' => null,
            'serving_quantity' => 2,
            'serving_quantity_unit' => 'g',
            'nutrition_data_per' => '100g',
            'nutrition_data_per_imported' => '100g',
            'nutriments' => [
                'proteins_100g' => 0,
                'carbohydrates_100g' => 0,
                'fat_100g' => 0,
                'sodium_100g' => 0,
                'salt_100g' => 0,
            ],
            'ingredients_tags' => [
                'en:orange-pekoe-and-pekoe-cut-black-teas',
            ],
            'additives_tags' => [],
            'categories_tags' => [
                'en:beverages',
                'en:hot-beverages',
                'en:teas',
                'en:decaffeinated-teas',
                'en:tea-bags',
            ],
            'food_groups_tags' => [
                'en:beverages',
                'en:unsweetened-beverages',
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0047900313502')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(0, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(0, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0, $food->sugar);
        $this->assertEquals(0, $food->sodium);
        $this->assertEquals(0, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertNull($food->package_quantity);
        $this->assertNull($food->package_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_imports_verified_water_without_an_ingredient_list(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '00394567',
            'product_name' => 'Still Scottish Mountain Water',
            'product_quantity' => 500,
            'product_quantity_unit' => 'ml',
            'nutriments' => [],
            'ingredients_tags' => [],
            'ingredients' => [],
            'categories_tags' => [
                'en:beverages',
                'en:waters',
                'en:spring-waters',
            ],
            'nutriscore_data' => ['is_water' => '1'],
            'nutrition' => [
                'aggregated_set' => [
                    'preparation' => 'as_sold',
                    'per' => '100ml',
                    'nutrients' => [
                        'nova-group' => ['value' => 1, 'unit' => ''],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '00394567')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(0, $food->calories);
        $this->assertEquals(0, $food->protein);
        $this->assertEquals(0, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(0, $food->fibre);
        $this->assertEquals(0, $food->sugar);
        $this->assertEquals(0, $food->sodium);
        $this->assertEquals(0, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('ml', $food->nutrition_basis_unit);
        $this->assertEquals(500, $food->package_quantity);
        $this->assertSame('ml', $food->package_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_rejects_water_category_without_a_second_signal(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'nutriments' => [],
            'ingredients_tags' => [],
            'categories_tags' => ['en:waters'],
            'nutriscore_data' => [],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseCount('foods', 0);
    }

    public function test_it_estimates_missing_energy_from_complete_per_100_macros(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0027000388402',
            'product_name' => 'Organic Diced Tomatoes',
            'serving_quantity' => 130,
            'serving_quantity_unit' => 'g',
            'nutrition_data_per' => '100g',
            'nutrition_data_per_imported' => '100g',
            'nutriments' => [
                'proteins_100g' => 0.77,
                'carbohydrates_100g' => 4.62,
                'fat_100g' => 0,
                'fiber_100g' => 1.5,
                'sugars_100g' => 2.31,
                'saturated-fat_100g' => 0,
                'sodium_100g' => 0.154,
                'salt_100g' => 0.385,
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0027000388402')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(21.56, $food->calories);
        $this->assertEquals(0.77, $food->protein);
        $this->assertEquals(4.62, $food->carbohydrates);
        $this->assertEquals(0, $food->fat);
        $this->assertEquals(1.5, $food->fibre);
        $this->assertEquals(2.31, $food->sugar);
        $this->assertEquals(0.15, $food->sodium);
        $this->assertEquals(0.39, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_uses_prepared_nutrition_only_as_a_last_resort(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'code' => '0039978302564',
            'product_name' => 'Scottish Oatmeal Raisin Scone Mix',
            'product_quantity' => 567,
            'product_quantity_unit' => 'g',
            'serving_quantity' => 47,
            'serving_quantity_unit' => 'g',
            'nutriments' => [],
            'nutrition' => [
                'aggregated_set' => [
                    'preparation' => 'prepared',
                    'per' => '100g',
                    'nutrients' => [
                        'energy-kcal' => [
                            'value' => 363.82978723404,
                            'unit' => 'kcal',
                        ],
                        'proteins' => [
                            'value' => 8.5106382978723,
                            'unit' => 'g',
                        ],
                        'carbohydrates' => [
                            'value' => 74.468085106383,
                            'unit' => 'g',
                        ],
                        'fat' => [
                            'value' => 2.1276595744681,
                            'unit' => 'g',
                        ],
                        'fiber' => [
                            'value' => 6.3829787234043,
                            'unit' => 'g',
                        ],
                        'sugars' => [
                            'value' => 23.404255319149,
                            'unit' => 'g',
                        ],
                        'saturated-fat' => [
                            'value' => 0,
                            'unit' => 'g',
                        ],
                        'sodium' => [
                            'value' => 0.73497872340426,
                            'unit' => 'g',
                        ],
                        'salt' => [
                            'value' => 1.8374468085106,
                            'unit' => 'g',
                        ],
                    ],
                ],
                'input_sets' => [
                    [
                        'preparation' => 'as_sold',
                        'source' => 'packaging',
                        'per' => 'serving',
                        'per_quantity' => 47,
                        'per_unit' => 'g',
                        'nutrients' => [
                            'nova-group' => ['value' => 4, 'unit' => ''],
                        ],
                    ],
                ],
            ],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $food = DB::table('foods')
            ->where('external_id', '0039978302564')
            ->first();
        $this->assertNotNull($food);
        $this->assertEquals(363.83, $food->calories);
        $this->assertEquals(8.51, $food->protein);
        $this->assertEquals(74.47, $food->carbohydrates);
        $this->assertEquals(2.13, $food->fat);
        $this->assertEquals(6.38, $food->fibre);
        $this->assertEquals(23.4, $food->sugar);
        $this->assertEquals(0, $food->saturated_fat);
        $this->assertEquals(0.73, $food->sodium);
        $this->assertEquals(1.84, $food->salt);
        $this->assertEquals(100, $food->nutrition_basis_amount);
        $this->assertSame('g', $food->nutrition_basis_unit);
        $this->assertEquals(567, $food->package_quantity);
        $this->assertSame('g', $food->package_unit);
        $this->assertSame(1, $food->nutrition_complete);
    }

    public function test_it_does_not_assume_flavoured_water_has_zero_nutrition(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'nutriments' => [],
            'ingredients_tags' => ['en:water', 'en:sugar'],
            'categories_tags' => ['en:waters'],
            'nutriscore_data' => ['is_water' => '1'],
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseCount('foods', 0);
    }

    public function test_it_does_not_treat_unscaled_serving_values_as_per_100(): void
    {
        $product = [
            ...$this->romanianProduct(),
            'nutriments' => [
                'energy-kcal' => 200,
                'proteins' => 5,
            ],
            'nutrition_data_per' => 'serving',
        ];
        $path = $this->gzipDump([$product]);

        $status = Artisan::call('foods:import-open-food-facts', [
            'path' => $path,
            '--scope' => 'all',
        ]);

        $this->assertSame(0, $status, Artisan::output());
        $this->assertDatabaseCount('foods', 0);
        $run = DB::table('food_import_runs')->latest('id')->first();
        $this->assertSame(
            ['missing_energy' => 1],
            json_decode($run->skip_reasons, true)
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
