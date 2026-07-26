<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date']);
        });

        Schema::create('diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('diary_day_id')->constrained()->cascadeOnDelete();
            $table->foreignId('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('meal', 20);
            $table->string('food_name');
            $table->string('brand')->nullable();
            $table->string('unit_type', 16)->default('g');
            $table->string('serving_name')->default('100 g');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('amount', 8, 2);
            $table->decimal('calories', 8, 2);
            $table->decimal('protein', 8, 2)->default(0);
            $table->decimal('carbohydrates', 8, 2)->default(0);
            $table->decimal('fat', 8, 2)->default(0);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['diary_day_id', 'meal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_entries');
        Schema::dropIfExists('diary_days');
    }
};
