<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_sources', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('license')->nullable();
            $table->text('attribution_url')->nullable();
            $table->timestamps();
        });

        DB::table('food_sources')->insert([
            [
                'code' => 'open_food_facts',
                'name' => 'Open Food Facts',
                'license' => 'Open Database License (ODbL)',
                'attribution_url' => 'https://world.openfoodfacts.org/',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('food_sources');
    }
};
