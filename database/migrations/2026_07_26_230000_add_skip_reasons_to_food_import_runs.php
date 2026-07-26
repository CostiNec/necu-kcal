<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_import_runs', function (Blueprint $table) {
            $table->json('skip_reasons')
                ->nullable()
                ->after('skipped_count');
        });
    }

    public function down(): void
    {
        Schema::table('food_import_runs', function (Blueprint $table) {
            $table->dropColumn('skip_reasons');
        });
    }
};
