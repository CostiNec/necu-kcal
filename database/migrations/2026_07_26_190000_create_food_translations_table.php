<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')
                ->constrained('foods')
                ->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');
            $table->timestamps();

            $table->unique(['food_id', 'locale']);
            $table->index(
                ['locale', 'name', 'food_id'],
                'food_translations_locale_name_food_idx'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE food_translations ADD FULLTEXT INDEX food_translations_name_fulltext (name)'
            );
        }

        $this->backfillTranslations();

        Schema::table('foods', function (Blueprint $table) {
            $table->dropUnique(['translation_key']);
            $table->dropColumn('translation_key');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn('food_translation_key');
        });

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->dropColumn('food_translation_key');
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('translation_key')->nullable()->unique()->after('name');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->string('food_translation_key')
                ->nullable()
                ->after('food_name');
        });

        Schema::table('recipe_ingredients', function (Blueprint $table) {
            $table->string('food_translation_key')
                ->nullable()
                ->after('food_name');
        });

        Schema::dropIfExists('food_translations');
    }

    private function backfillTranslations(): void
    {
        $translations = [
            'en' => [
                'chicken_breast' => 'Chicken breast, cooked',
                'whole_egg' => 'Whole egg',
                'greek_yogurt' => 'Greek yogurt 2%',
                'rolled_oats' => 'Oats, rolled',
                'white_rice' => 'White rice, cooked',
                'boiled_potato' => 'Potato, boiled',
                'banana' => 'Banana',
                'apple' => 'Apple',
                'avocado' => 'Avocado',
                'almonds' => 'Almonds',
                'whole_milk' => 'Whole milk',
                'baked_salmon' => 'Salmon, baked',
            ],
            'ro' => [
                'chicken_breast' => 'Piept de pui, gătit',
                'whole_egg' => 'Ou întreg',
                'greek_yogurt' => 'Iaurt grecesc 2%',
                'rolled_oats' => 'Fulgi de ovăz',
                'white_rice' => 'Orez alb, fiert',
                'boiled_potato' => 'Cartof fiert',
                'banana' => 'Banană',
                'apple' => 'Măr',
                'avocado' => 'Avocado',
                'almonds' => 'Migdale',
                'whole_milk' => 'Lapte integral',
                'baked_salmon' => 'Somon la cuptor',
            ],
        ];

        DB::table('foods')
            ->whereNotNull('translation_key')
            ->select(['id', 'translation_key'])
            ->orderBy('id')
            ->chunkById(500, function ($foods) use ($translations) {
                $rows = [];
                $now = now();

                foreach ($foods as $food) {
                    $key = str_starts_with($food->translation_key, 'foods.')
                        ? substr($food->translation_key, 6)
                        : $food->translation_key;

                    foreach ($translations as $locale => $names) {
                        if (! isset($names[$key])) {
                            continue;
                        }

                        $rows[] = [
                            'food_id' => $food->id,
                            'locale' => $locale,
                            'name' => $names[$key],
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('food_translations')->insertOrIgnore($rows);
                }
            });
    }
};
