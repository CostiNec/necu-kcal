<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')
                ->nullable()
                ->unique()
                ->constrained('foods')
                ->nullOnDelete();
            $table->string('name');
            $table->decimal('cooked_weight', 10, 2);
            $table->decimal('total_calories', 10, 2);
            $table->decimal('total_protein', 10, 2)->default(0);
            $table->decimal('total_carbohydrates', 10, 2)->default(0);
            $table->decimal('total_fat', 10, 2)->default(0);
            $table->decimal('total_fibre', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')
                ->nullable()
                ->constrained('foods')
                ->nullOnDelete();
            $table->string('food_name');
            $table->string('food_translation_key')->nullable();
            $table->decimal('amount', 10, 2);
            $table->decimal('calories', 8, 2);
            $table->decimal('protein', 8, 2)->default(0);
            $table->decimal('carbohydrates', 8, 2)->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->decimal('fibre', 8, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
