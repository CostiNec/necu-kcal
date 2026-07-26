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
            // Product is the bulk catalogue default, minimizing backfill work.
            $table->unsignedTinyInteger('search_priority')
                ->default(2);
        });

        DB::table('foods')
            ->where('food_type', 'generic')
            ->update(['search_priority' => 0]);

        DB::table('foods')
            ->whereIn('food_type', ['custom', 'recipe'])
            ->update(['search_priority' => 1]);
    }

    public function down(): void
    {
        Schema::table('foods', function (Blueprint $table) {
            $table->dropColumn('search_priority');
        });
    }
};
