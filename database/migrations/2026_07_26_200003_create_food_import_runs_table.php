<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('food_import_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('source_id');
            $table->string('status', 24)->default('pending');
            $table->string('file_name');
            $table->char('file_checksum', 64)->nullable();
            $table->unsignedInteger('source_schema_version')->nullable();
            $table->unsignedBigInteger('processed_count')->default(0);
            $table->unsignedBigInteger('inserted_count')->default(0);
            $table->unsignedBigInteger('updated_count')->default(0);
            $table->unsignedBigInteger('skipped_count')->default(0);
            $table->unsignedBigInteger('error_count')->default(0);
            $table->unsignedBigInteger('last_processed_line')->default(0);
            $table->string('last_external_id', 64)->nullable();
            $table->json('options')->nullable();
            $table->text('error_message')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('source_id')
                ->references('id')
                ->on('food_sources')
                ->restrictOnDelete();
            $table->index(
                ['source_id', 'status'],
                'food_import_runs_source_status_idx'
            );
            $table->index(
                ['source_id', 'started_at'],
                'food_import_runs_source_started_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('food_import_runs');
    }
};
