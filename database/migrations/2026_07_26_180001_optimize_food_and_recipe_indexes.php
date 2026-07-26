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
            $table->index(
                ['is_public', 'name', 'id'],
                'foods_public_name_id_idx'
            );
            $table->index(
                ['user_id', 'name', 'id'],
                'foods_user_name_id_idx'
            );
            $table->index('brand', 'foods_brand_idx');
        });

        Schema::table('recipes', function (Blueprint $table) {
            $table->index(
                ['user_id', 'created_at', 'id'],
                'recipes_user_created_id_idx'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE foods ADD FULLTEXT INDEX foods_name_brand_fulltext (name, brand)'
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE foods DROP INDEX foods_name_brand_fulltext'
            );
        }

        Schema::table('recipes', function (Blueprint $table) {
            $table->dropIndex('recipes_user_created_id_idx');
        });

        Schema::table('foods', function (Blueprint $table) {
            $table->dropIndex('foods_public_name_id_idx');
            $table->dropIndex('foods_user_name_id_idx');
            $table->dropIndex('foods_brand_idx');
        });
    }
};
