<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('foods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('brand')->nullable();
            $table->string('barcode', 64)->nullable()->index();
            $table->decimal('calories', 8, 2);
            $table->decimal('protein', 8, 2)->nullable();
            $table->decimal('carbohydrates', 8, 2)->nullable();
            $table->decimal('fat', 8, 2)->nullable();
            $table->decimal('fibre', 8, 2)->nullable();
            $table->decimal('sugar', 8, 2)->nullable();
            $table->decimal('sodium', 8, 2)->nullable();
            $table->string('unit_type', 16)->default('g');
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->index(['name', 'brand']);
            $table->index(['user_id', 'is_public']);
        });

        Schema::create('food_servings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 8, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('food_favourites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->constrained('foods')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'food_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_favourites');
        Schema::dropIfExists('food_servings');
        Schema::dropIfExists('foods');
    }
};
