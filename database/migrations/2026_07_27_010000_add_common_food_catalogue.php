<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // One table rebuild matters when foods already contains millions
            // of rows. Schema builder emits several ALTER statements here.
            $indexes = collect(Schema::getIndexes('foods'))
                ->pluck('name')
                ->all();
            $clauses = [];

            if (! Schema::hasColumn('foods', 'canonical_food_id')) {
                $clauses[] = 'ADD canonical_food_id BIGINT UNSIGNED NULL AFTER source_id';
                $clauses[] = 'ADD INDEX foods_canonical_food_id_idx (canonical_food_id)';
            }

            if (! Schema::hasColumn('foods', 'nutrition_source_food_id')) {
                $clauses[] = 'ADD nutrition_source_food_id BIGINT UNSIGNED NULL AFTER canonical_food_id';
                $clauses[] = 'ADD INDEX foods_nutrition_source_food_id_idx (nutrition_source_food_id)';
            }

            if (! Schema::hasColumn('foods', 'is_common')) {
                $clauses[] = 'ADD is_common TINYINT(1) NOT NULL DEFAULT 0 AFTER search_priority';
            }

            if (! Schema::hasColumn('foods', 'common_priority')) {
                $clauses[] = 'ADD common_priority SMALLINT UNSIGNED NULL AFTER is_common';
            }

            if (! in_array('foods_search_visibility_idx', $indexes, true)) {
                $clauses[] = 'ADD INDEX foods_search_visibility_idx (is_active, canonical_food_id, search_priority, id)';
            }

            if (! in_array('foods_common_priority_idx', $indexes, true)) {
                $clauses[] = 'ADD INDEX foods_common_priority_idx (is_common, common_priority, id)';
            }

            if ($clauses !== []) {
                DB::statement(
                    'ALTER TABLE foods '.implode(', ', $clauses)
                );
            }
        } else {
            Schema::table('foods', function (Blueprint $table) {
                $table->foreignId('canonical_food_id')
                    ->nullable()
                    ->after('source_id')
                    ->index('foods_canonical_food_id_idx');
                $table->foreignId('nutrition_source_food_id')
                    ->nullable()
                    ->after('canonical_food_id')
                    ->index('foods_nutrition_source_food_id_idx');
                $table->boolean('is_common')
                    ->default(false)
                    ->after('search_priority');
                $table->unsignedSmallInteger('common_priority')
                    ->nullable()
                    ->after('is_common');

                $table->index(
                    ['is_active', 'canonical_food_id', 'search_priority', 'id'],
                    'foods_search_visibility_idx'
                );
                $table->index(
                    ['is_common', 'common_priority', 'id'],
                    'foods_common_priority_idx'
                );
            });
        }

        Schema::create('food_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')
                ->constrained('foods')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->string('alias_type', 24)->default('synonym');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->string('source', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['food_id', 'locale', 'name'],
                'food_aliases_food_locale_name_unique'
            );
            $table->index(
                ['locale', 'name', 'food_id'],
                'food_aliases_locale_name_food_idx'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE food_aliases ADD FULLTEXT INDEX food_aliases_name_fulltext (name)'
            );
        }

        $sources = [
            [
                'code' => 'canadian_nutrient_file',
                'name' => 'Canadian Nutrient File',
                'license' => 'Open Government Licence - Canada',
                'attribution_url' => 'https://food-nutrition.canada.ca/cnf-fce/',
            ],
            [
                'code' => 'fineli',
                'name' => 'Fineli Food Composition Database',
                'license' => 'Creative Commons Attribution 4.0',
                'attribution_url' => 'https://fineli.fi/fineli/en/index',
            ],
            [
                'code' => 'cofid',
                'name' => 'UK Composition of Foods Integrated Dataset',
                'license' => 'Open Government Licence v3.0',
                'attribution_url' => 'https://www.gov.uk/government/publications/composition-of-foods-integrated-dataset-cofid',
            ],
            [
                'code' => 'afcd',
                'name' => 'Australian Food Composition Database',
                'license' => 'Creative Commons Attribution-ShareAlike 3.0 Australia',
                'attribution_url' => 'https://www.foodstandards.gov.au/science-data/monitoringnutrients/afcd',
            ],
            [
                'code' => 'efsa_eu_fcdb',
                'name' => 'EFSA European Food Composition Database',
                'license' => 'EFSA open data terms',
                'attribution_url' => 'https://www.efsa.europa.eu/en/data-report/food-composition',
            ],
            [
                'code' => 'curated_common',
                'name' => 'Kcal Curated Common Foods',
                'license' => 'Application-maintained mapping layer',
                'attribution_url' => null,
            ],
        ];

        foreach ($sources as $source) {
            DB::table('food_sources')->updateOrInsert(
                ['code' => $source['code']],
                [
                    ...$source,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE food_aliases DROP INDEX food_aliases_name_fulltext'
            );
        }

        Schema::dropIfExists('food_aliases');

        if (DB::getDriverName() === 'mysql') {
            $foreignKeys = collect(DB::select(<<<'SQL'
                SELECT CONSTRAINT_NAME
                FROM information_schema.KEY_COLUMN_USAGE
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'foods'
                    AND COLUMN_NAME IN (
                        'canonical_food_id',
                        'nutrition_source_food_id'
                    )
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                SQL))->pluck('CONSTRAINT_NAME');
            $indexes = collect(Schema::getIndexes('foods'))
                ->pluck('name');
            $clauses = $foreignKeys
                ->map(fn (string $name) => "DROP FOREIGN KEY `{$name}`")
                ->all();

            foreach ([
                'foods_canonical_food_id_idx',
                'foods_nutrition_source_food_id_idx',
                'foods_search_visibility_idx',
                'foods_common_priority_idx',
            ] as $index) {
                if ($indexes->contains($index)) {
                    $clauses[] = "DROP INDEX `{$index}`";
                }
            }

            foreach ([
                'canonical_food_id',
                'nutrition_source_food_id',
                'is_common',
                'common_priority',
            ] as $column) {
                if (Schema::hasColumn('foods', $column)) {
                    $clauses[] = "DROP COLUMN `{$column}`";
                }
            }

            if ($clauses !== []) {
                DB::statement(
                    'ALTER TABLE foods '.implode(', ', $clauses)
                );
            }
        } else {
            Schema::table('foods', function (Blueprint $table) {
                $table->dropIndex('foods_canonical_food_id_idx');
                $table->dropIndex('foods_nutrition_source_food_id_idx');
                $table->dropIndex('foods_search_visibility_idx');
                $table->dropIndex('foods_common_priority_idx');
                $table->dropColumn([
                    'canonical_food_id',
                    'nutrition_source_food_id',
                    'is_common',
                    'common_priority',
                ]);
            });
        }

        DB::table('food_sources')
            ->whereIn('code', [
                'canadian_nutrient_file',
                'fineli',
                'cofid',
                'afcd',
                'efsa_eu_fcdb',
                'curated_common',
            ])
            ->delete();
    }
};
