<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_markets', function (Blueprint $table) {
            $table->char('country_code', 2);
            $table->foreignId('food_id')
                ->constrained('foods')
                ->cascadeOnDelete();

            $table->primary(['country_code', 'food_id']);
            $table->index(
                ['food_id', 'country_code'],
                'food_markets_food_country_idx'
            );
        });

        Schema::create('stores', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('source_id')->nullable();
            $table->string('external_key', 128)->nullable();
            $table->string('name');

            $table->foreign('source_id')
                ->references('id')
                ->on('food_sources')
                ->nullOnDelete();
            $table->unique(
                ['source_id', 'external_key'],
                'stores_source_external_unique'
            );
            $table->index('name', 'stores_name_idx');
        });

        Schema::create('food_store', function (Blueprint $table) {
            $table->foreignId('store_id')
                ->constrained('stores')
                ->cascadeOnDelete();
            $table->foreignId('food_id')
                ->constrained('foods')
                ->cascadeOnDelete();

            $table->primary(['store_id', 'food_id']);
            $table->index(
                ['food_id', 'store_id'],
                'food_store_food_store_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_store');
        Schema::dropIfExists('stores');
        Schema::dropIfExists('food_markets');
    }
};
