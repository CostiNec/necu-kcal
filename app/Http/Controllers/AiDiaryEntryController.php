<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AiDiaryEntryController extends Controller
{
    public function create(Request $request, string $date): Response
    {
        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && CarbonImmutable::createFromFormat('Y-m-d', $date) !== false,
            404
        );

        $meal = $request->string('meal')->toString();

        abort_unless(
            in_array($meal, ['breakfast', 'lunch', 'dinner', 'snacks'], true),
            404
        );

        return Inertia::render('diary/ai-add', [
            'date' => $date,
            'meal' => $meal,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'meal' => ['required', 'in:breakfast,lunch,dinner,snacks'],
            'entries' => ['required', 'array', 'min:1', 'max:15'],
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

        DB::transaction(function () use ($request, $validated): void {
            $date = CarbonImmutable::createFromFormat(
                'Y-m-d',
                $validated['date']
            )->startOfDay();
            $day = DiaryDay::firstOrCreate([
                'user_id' => $request->user()->id,
                'date' => $date,
            ]);
            $position = (int) $day->entries()
                ->where('meal', $validated['meal'])
                ->max('position');

            foreach ($validated['entries'] as $entry) {
                $weight = (float) $entry['weight_grams'];
                $factor = $weight / 100;
                $position++;

                $day->entries()->create([
                    'food_id' => null,
                    'meal' => $validated['meal'],
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
                    'position' => $position,
                ]);
            }
        });

        return redirect()
            ->route('diary.show', [
                'date' => $validated['date'],
                'focus_meal' => $validated['meal'],
            ])
            ->with('success', __('app.ai_entries_added', [
                'count' => count($validated['entries']),
            ]));
    }
}
