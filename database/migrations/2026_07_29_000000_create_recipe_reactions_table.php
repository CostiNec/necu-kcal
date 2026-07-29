<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reaction', 7);
            $table->timestamps();

            $table->unique(['recipe_id', 'user_id']);
            $table->index(['recipe_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_reactions');
    }
};
