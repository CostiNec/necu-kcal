<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('food_sources')->updateOrInsert(
            ['code' => 'usda_food_data_central'],
            [
                'name' => 'USDA FoodData Central',
                'license' => 'Public domain (U.S. government work)',
                'attribution_url' => 'https://fdc.nal.usda.gov/',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('food_sources')
            ->where('code', 'usda_food_data_central')
            ->delete();
    }
};
