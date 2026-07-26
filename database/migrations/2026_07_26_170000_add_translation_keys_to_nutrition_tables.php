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
            $table->string('translation_key')
                ->nullable()
                ->unique()
                ->after('name');
        });

        Schema::table('food_servings', function (Blueprint $table) {
            $table->string('translation_key')
                ->nullable()
                ->after('name');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->string('food_translation_key')
                ->nullable()
                ->after('food_name');
            $table->string('serving_translation_key')
                ->nullable()
                ->after('serving_name');
        });

        $this->backfillCommonFoodTranslationKeys();
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn([
                'food_translation_key',
                'serving_translation_key',
            ]);
        });

        Schema::table('food_servings', function (Blueprint $table) {
            $table->dropColumn('translation_key');
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->dropUnique(['translation_key']);
            $table->dropColumn('translation_key');
        });
    }

    private function backfillCommonFoodTranslationKeys(): void
    {
        $foods = [
            ['chicken_breast', 'Chicken breast, cooked', '100_g', '100 g'],
            ['whole_egg', 'Whole egg', 'large_egg', '1 large egg'],
            ['greek_yogurt', 'Greek yogurt 2%', 'cup', '1 cup'],
            ['rolled_oats', 'Oats, rolled', 'bowl', '1 bowl'],
            ['white_rice', 'White rice, cooked', 'cup', '1 cup'],
            ['boiled_potato', 'Potato, boiled', 'medium_piece', '1 medium'],
            ['banana', 'Banana', 'medium_piece', '1 medium'],
            ['apple', 'Apple', 'medium_piece', '1 medium'],
            ['avocado', 'Avocado', 'half_avocado', '1/2 avocado'],
            ['almonds', 'Almonds', 'handful', '1 handful'],
            ['whole_milk', 'Whole milk', 'glass', '1 glass'],
            ['baked_salmon', 'Salmon, baked', 'fillet', '1 fillet'],
        ];

        foreach ($foods as [$foodKey, $foodName, $servingKey, $servingName]) {
            $foodId = DB::table('foods')
                ->whereNull('user_id')
                ->where('name', $foodName)
                ->value('id');

            if ($foodId === null) {
                continue;
            }

            $foodTranslationKey = "foods.{$foodKey}";
            $servingTranslationKey = "servings.{$servingKey}";

            DB::table('foods')
                ->where('id', $foodId)
                ->update(['translation_key' => $foodTranslationKey]);

            DB::table('food_servings')
                ->where('food_id', $foodId)
                ->where('name', $servingName)
                ->update(['translation_key' => $servingTranslationKey]);

            DB::table('diary_entries')
                ->where('food_id', $foodId)
                ->update(['food_translation_key' => $foodTranslationKey]);

            DB::table('diary_entries')
                ->where('food_id', $foodId)
                ->where('serving_name', $servingName)
                ->update(['serving_translation_key' => $servingTranslationKey]);
        }
    }
};
