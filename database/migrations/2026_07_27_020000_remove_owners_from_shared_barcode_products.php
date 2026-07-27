<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('foods')
            ->where('food_type', 'product')
            ->whereNotNull('barcode')
            ->where('is_public', true)
            ->whereNotNull('user_id')
            ->update(['user_id' => null]);
    }

    public function down(): void
    {
        // The original creator cannot be reconstructed reliably.
    }
};
