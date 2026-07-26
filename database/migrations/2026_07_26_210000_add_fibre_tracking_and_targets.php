<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nutrition_targets', function (Blueprint $table) {
            $table->unsignedSmallInteger('fibre')->default(30)->after('fat');
        });

        Schema::table('diary_entries', function (Blueprint $table) {
            $table->decimal('fibre', 8, 2)->default(0)->after('fat');
        });

        $this->backfillDiaryFibre();
    }

    public function down(): void
    {
        Schema::table('diary_entries', function (Blueprint $table) {
            $table->dropColumn('fibre');
        });

        Schema::table('nutrition_targets', function (Blueprint $table) {
            $table->dropColumn('fibre');
        });
    }

    private function backfillDiaryFibre(): void
    {
        DB::table('diary_entries')
            ->whereNotNull('food_id')
            ->select(['id', 'food_id', 'amount'])
            ->orderBy('id')
            ->chunkById(500, function ($entries): void {
                $fibreByFood = DB::table('foods')
                    ->whereIn('id', $entries->pluck('food_id')->unique())
                    ->pluck('fibre', 'id');

                foreach ($entries as $entry) {
                    $fibrePerHundred = $fibreByFood->get($entry->food_id);

                    if ($fibrePerHundred === null) {
                        continue;
                    }

                    DB::table('diary_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'fibre' => round(
                                (float) $fibrePerHundred
                                * ((float) $entry->amount / 100),
                                2
                            ),
                        ]);
                }
            });
    }
};
