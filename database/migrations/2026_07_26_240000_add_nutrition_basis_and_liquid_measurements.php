<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('foods', 'nutrition_basis_amount')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->decimal('nutrition_basis_amount', 10, 3)
                    ->default(100)
                    ->after('calories');
            });
        }

        if (! Schema::hasColumn('foods', 'nutrition_basis_unit')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->string('nutrition_basis_unit', 8)
                    ->default('g')
                    ->after('nutrition_basis_amount');
            });
        }

        if (! Schema::hasColumn('diary_entries', 'total_milliliters')) {
            Schema::table('diary_entries', function (Blueprint $table) {
                $table->decimal('total_milliliters', 12, 3)
                    ->nullable()
                    ->after('total_grams');
            });
        }

        if (! Schema::hasColumn('recipe_ingredients', 'unit')) {
            Schema::table('recipe_ingredients', function (Blueprint $table) {
                $table->string('unit', 8)
                    ->default('g')
                    ->after('amount');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipe_ingredients', 'unit')) {
            Schema::table('recipe_ingredients', function (Blueprint $table) {
                $table->dropColumn('unit');
            });
        }

        if (Schema::hasColumn('diary_entries', 'total_milliliters')) {
            Schema::table('diary_entries', function (Blueprint $table) {
                $table->dropColumn('total_milliliters');
            });
        }

        if (Schema::hasColumn('foods', 'nutrition_basis_unit')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->dropColumn('nutrition_basis_unit');
            });
        }

        if (Schema::hasColumn('foods', 'nutrition_basis_amount')) {
            Schema::table('foods', function (Blueprint $table) {
                $table->dropColumn('nutrition_basis_amount');
            });
        }
    }
};
