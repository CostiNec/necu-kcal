<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('food_translations', function (Blueprint $table) {
            $table->string('translation_source', 32)
                ->default('imported')
                ->after('name');
            $table->timestamp('reviewed_at')
                ->nullable()
                ->after('translation_source');

            $table->index(
                ['locale', 'translation_source'],
                'food_translations_locale_source_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('food_translations', function (Blueprint $table) {
            $table->dropIndex('food_translations_locale_source_idx');
            $table->dropColumn(['translation_source', 'reviewed_at']);
        });
    }
};
