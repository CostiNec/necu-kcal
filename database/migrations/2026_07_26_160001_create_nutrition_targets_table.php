<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nutrition_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('calories')->default(2000);
            $table->unsignedSmallInteger('protein')->default(120);
            $table->unsignedSmallInteger('carbohydrates')->default(220);
            $table->unsignedSmallInteger('fat')->default(65);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nutrition_targets');
    }
};
