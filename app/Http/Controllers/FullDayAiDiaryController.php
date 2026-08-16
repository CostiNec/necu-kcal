<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FullDayAiDiaryController extends Controller
{
    public function today(Request $request): RedirectResponse
    {
        $timezone = $request->user()->profile?->timezone
            ?? config('app.timezone');

        return redirect()->route('diary-entries.ai.day.create', [
            'date' => CarbonImmutable::now($timezone)->toDateString(),
        ]);
    }

    public function create(string $date): Response
    {
        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && CarbonImmutable::createFromFormat('Y-m-d', $date) !== false,
            404
        );

        return Inertia::render('diary/ai-day', ['date' => $date]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'entries' => ['required', 'array', 'min:1', 'max:30'],
            'entries.*.meal' => [
                'required',
                'in:breakfast,lunch,dinner,snacks',
            ],
            'entries.*.name' => ['required', 'string', 'max:255'],
            'entries.*.weight_grams' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000',
            ],
            'entries.*.calories_per_100g' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100000',
            ],
            'entries.*.protein_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'entries.*.carbohydrates_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'entries.*.fat_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'entries.*.fibre_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
        ]);

        $entryIds = DB::transaction(function () use ($request, $validated): array {
            $date = CarbonImmutable::createFromFormat(
                'Y-m-d',
                $validated['date']
            )->startOfDay();
            $day = DiaryDay::firstOrCreate([
                'user_id' => $request->user()->id,
                'date' => $date,
            ]);
            $positions = $day->entries()
                ->selectRaw('meal, MAX(position) as max_position')
                ->groupBy('meal')
                ->pluck('max_position', 'meal')
                ->map(fn ($position): int => (int) $position)
                ->all();
            $entryIds = [];

            foreach ($validated['entries'] as $entry) {
                $meal = $entry['meal'];
                $weight = (float) $entry['weight_grams'];
                $factor = $weight / 100;
                $positions[$meal] = ($positions[$meal] ?? 0) + 1;

                $createdEntry = $day->entries()->create([
                    'food_id' => null,
                    'meal' => $meal,
                    'food_name' => trim($entry['name']),
                    'brand' => null,
                    'unit' => 'g',
                    'quantity' => 1,
                    'amount' => $weight,
                    'total_grams' => $weight,
                    'total_milliliters' => null,
                    'calories' => round(
                        (float) $entry['calories_per_100g'] * $factor,
                        2
                    ),
                    'protein' => round(
                        (float) $entry['protein_per_100g'] * $factor,
                        2
                    ),
                    'carbohydrates' => round(
                        (float) $entry['carbohydrates_per_100g'] * $factor,
                        2
                    ),
                    'fat' => round(
                        (float) $entry['fat_per_100g'] * $factor,
                        2
                    ),
                    'fibre' => round(
                        (float) $entry['fibre_per_100g'] * $factor,
                        2
                    ),
                    'position' => $positions[$meal],
                ]);
                $entryIds[] = $createdEntry->id;
            }

            return $entryIds;
        });

        return redirect()
            ->route('diary.show', [
                'date' => $validated['date'],
                'added_entries' => implode(',', $entryIds),
            ])
            ->with('success', __('app.ai_day_entries_added', [
                'count' => count($validated['entries']),
            ]));
    }
}
