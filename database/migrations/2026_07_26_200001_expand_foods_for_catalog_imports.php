<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->unsignedSmallInteger('source_id')->nullable()->after('user_id');
            $table->string('external_id', 64)->nullable()->after('source_id');
            $table->string('food_type', 16)->default('generic')->after('external_id');
            $table->char('main_locale', 2)->nullable()->after('brand');
            $table->decimal('saturated_fat', 8, 2)->nullable()->after('fat');
            $table->decimal('salt', 8, 2)->nullable()->after('sodium');
            $table->decimal('serving_size', 10, 3)->nullable()->after('unit_type');
            $table->string('serving_unit', 16)->nullable()->after('serving_size');
            $table->decimal('package_quantity', 10, 3)->nullable()->after('serving_unit');
            $table->string('package_unit', 16)->nullable()->after('package_quantity');
            $table->boolean('nutrition_complete')->default(false)->after('is_public');
            $table->boolean('is_active')->default(true)->after('nutrition_complete');
            $table->decimal('data_completeness', 5, 4)->nullable()->after('is_active');
            $table->unsignedBigInteger('popularity_score')->default(0)->after('data_completeness');
            $table->text('image_url')->nullable()->after('popularity_score');
            $table->text('search_text')->nullable()->after('image_url');
            $table->dateTime('source_updated_at')->nullable()->after('search_text');
            $table->dateTime('imported_at')->nullable()->after('source_updated_at');

            $table->foreign('source_id')
                ->references('id')
                ->on('food_sources')
                ->nullOnDelete();
            $table->unique(
                ['source_id', 'external_id'],
                'foods_source_external_unique'
            );
            $table->index(
                ['food_type', 'is_active', 'id'],
                'foods_type_active_id_idx'
            );
            $table->index(
                ['source_id', 'source_updated_at'],
                'foods_source_updated_idx'
            );
        });

        $this->classifyExistingFoods();
        $this->markCompleteNutrition();
        $this->backfillSearchText();

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE foods ADD FULLTEXT INDEX foods_search_text_fulltext (search_text)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE foods DROP INDEX foods_search_text_fulltext'
            );
        }

        Schema::table('foods', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropUnique('foods_source_external_unique');
            $table->dropIndex('foods_type_active_id_idx');
            $table->dropIndex('foods_source_updated_idx');
            $table->dropColumn([
                'source_id',
                'external_id',
                'food_type',
                'main_locale',
                'saturated_fat',
                'salt',
                'serving_size',
                'serving_unit',
                'package_quantity',
                'package_unit',
                'nutrition_complete',
                'is_active',
                'data_completeness',
                'popularity_score',
                'image_url',
                'search_text',
                'source_updated_at',
                'imported_at',
            ]);
        });
    }

    private function classifyExistingFoods(): void
    {
        DB::table('foods')
            ->whereNotNull('user_id')
            ->update(['food_type' => 'custom']);

        DB::table('foods')
            ->whereIn(
                'id',
                DB::table('recipes')
                    ->select('food_id')
                    ->whereNotNull('food_id')
            )
            ->update(['food_type' => 'recipe']);
    }

    private function markCompleteNutrition(): void
    {
        DB::table('foods')
            ->whereNotNull('protein')
            ->whereNotNull('carbohydrates')
            ->whereNotNull('fat')
            ->update(['nutrition_complete' => true]);
    }

    private function backfillSearchText(): void
    {
        DB::table('foods')
            ->select(['id', 'name', 'brand', 'barcode'])
            ->orderBy('id')
            ->chunkById(500, function ($foods): void {
                $translations = DB::table('food_translations')
                    ->whereIn('food_id', $foods->pluck('id'))
                    ->get(['food_id', 'name'])
                    ->groupBy('food_id');

                foreach ($foods as $food) {
                    $names = $translations
                        ->get($food->id, collect())
                        ->pluck('name');

                    $searchText = collect([
                        $food->name,
                        $food->brand,
                        $food->barcode,
                        ...$names->all(),
                    ])
                        ->filter(fn ($value) => is_string($value) && trim($value) !== '')
                        ->map(fn (string $value) => trim($value))
                        ->unique()
                        ->implode(' ');

                    DB::table('foods')
                        ->where('id', $food->id)
                        ->update(['search_text' => $searchText]);
                }
            });
    }
};
