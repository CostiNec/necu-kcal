<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->decimal('total_grams', 12, 3)
                ->nullable()
                ->after('amount');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->renameColumn('unit_type', 'unit');
        });

        DB::table('diary_entries')
            ->select(['id', 'quantity', 'amount'])
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    $quantity = max((float) $entry->quantity, 0.01);
                    $totalGrams = (float) $entry->amount;

                    DB::table('diary_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'unit' => 'g',
                            'amount' => round($totalGrams / $quantity, 3),
                            'total_grams' => round($totalGrams, 3),
                        ]);
                }
            });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn([
                'serving_name',
                'serving_translation_key',
            ]);
        });

        Schema::dropIfExists('food_servings');

        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn([
                'unit_type',
                'serving_size',
                'serving_unit',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->string('unit_type', 16)->default('g');
            $table->decimal('serving_size', 10, 3)->nullable();
            $table->string('serving_unit', 16)->nullable();
        });

        Schema::create('food_servings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('food_id')
                ->constrained('foods')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('translation_key')->nullable();
            $table->decimal('amount', 8, 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->string('serving_name')
                ->default('100 g')
                ->after('unit');
            $table->string('serving_translation_key')
                ->nullable()
                ->after('serving_name');
        });

        DB::table('diary_entries')
            ->select(['id', 'total_grams'])
            ->whereNotNull('total_grams')
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('diary_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'amount' => $entry->total_grams,
                            'serving_name' => '100 g',
                        ]);
                }
            });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn('total_grams');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->renameColumn('unit', 'unit_type');
        });
    }
};
