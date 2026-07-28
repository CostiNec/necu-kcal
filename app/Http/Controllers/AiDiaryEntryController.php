<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'name' => ['required', 'string', 'max:255'],
            'weight_grams' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000',
            ],
            'calories_per_100g' => [
                'required',
                'numeric',
                'min:0.01',
                'max:100000',
            ],
            'protein_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'carbohydrates_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'fat_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'fibre_per_100g' => [
                'required',
                'numeric',
                'min:0',
                'max:10000',
            ],
        ]);

        $day = DiaryDay::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
        ]);
        $weight = (float) $validated['weight_grams'];
        $factor = $weight / 100;

        $day->entries()->create([
            'food_id' => null,
            'meal' => $validated['meal'],
            'food_name' => trim($validated['name']),
            'brand' => null,
            'unit' => 'g',
            'quantity' => 1,
            'amount' => $weight,
            'total_grams' => $weight,
            'total_milliliters' => null,
            'calories' => round(
                (float) $validated['calories_per_100g'] * $factor,
                2
            ),
            'protein' => round(
                (float) $validated['protein_per_100g'] * $factor,
                2
            ),
            'carbohydrates' => round(
                (float) $validated['carbohydrates_per_100g'] * $factor,
                2
            ),
            'fat' => round(
                (float) $validated['fat_per_100g'] * $factor,
                2
            ),
            'fibre' => round(
                (float) $validated['fibre_per_100g'] * $factor,
                2
            ),
            'position' => (int) $day->entries()
                ->where('meal', $validated['meal'])
                ->max('position') + 1,
        ]);

        return redirect()
            ->route('diary.show', [
                'date' => $validated['date'],
                'focus_meal' => $validated['meal'],
            ])
            ->with('success', __('app.ai_entry_added'));
    }
}
