<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\Food;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DiaryEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'food_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'meal' => ['required', 'in:breakfast,lunch,dinner,snacks'],
            'serving_name' => ['required', 'string', 'max:100'],
            'serving_translation_key' => ['nullable', 'string', 'max:100'],
            'serving_amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $food = Food::query()
            ->visibleTo($request->user())
            ->with('translation')
            ->findOrFail($validated['food_id']);
        $day = DiaryDay::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
        ]);

        $amount = (float) $validated['serving_amount'] * (float) $validated['quantity'];
        $factor = $amount / 100;

        $day->entries()->create([
            'food_id' => $food->id,
            'meal' => $validated['meal'],
            'food_name' => $food->name,
            'brand' => $food->brand,
            'unit_type' => $food->unit_type,
            'serving_name' => $validated['serving_name'],
            'serving_translation_key' => $validated['serving_translation_key'] ?? null,
            'quantity' => $validated['quantity'],
            'amount' => round($amount, 2),
            'calories' => round($food->calories * $factor, 2),
            'protein' => round(($food->protein ?? 0) * $factor, 2),
            'carbohydrates' => round(($food->carbohydrates ?? 0) * $factor, 2),
            'fat' => round(($food->fat ?? 0) * $factor, 2),
            'position' => (int) $day->entries()->where('meal', $validated['meal'])->max('position') + 1,
        ]);

        return redirect()
            ->route('diary.show', ['date' => $validated['date']])
            ->with('success', __('app.food_added', [
                'food' => $food->localizedName(),
            ]));
    }

    public function update(Request $request, DiaryEntry $diaryEntry): RedirectResponse
    {
        abort_unless($diaryEntry->day->user_id === $request->user()->id, 403);
        $validated = $request->validate([
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $oldQuantity = max($diaryEntry->quantity, 0.01);
        $ratio = (float) $validated['quantity'] / $oldQuantity;
        $diaryEntry->update([
            'quantity' => $validated['quantity'],
            'amount' => round($diaryEntry->amount * $ratio, 2),
            'calories' => round($diaryEntry->calories * $ratio, 2),
            'protein' => round($diaryEntry->protein * $ratio, 2),
            'carbohydrates' => round($diaryEntry->carbohydrates * $ratio, 2),
            'fat' => round($diaryEntry->fat * $ratio, 2),
        ]);

        return back()->with('success', __('app.serving_updated'));
    }

    public function storeQuick(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'meal' => ['required', 'in:breakfast,lunch,dinner,snacks'],
            'name' => ['nullable', 'string', 'max:255'],
            'calories' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'protein' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'carbohydrates' => [
                'nullable',
                'numeric',
                'min:0',
                'max:10000',
            ],
            'fat' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        $day = DiaryDay::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
        ]);
        $name = trim((string) ($validated['name'] ?? ''));

        $day->entries()->create([
            'food_id' => null,
            'meal' => $validated['meal'],
            'food_name' => $name !== ''
                ? $name
                : __('app.quick_calorie_entry'),
            'brand' => null,
            'unit_type' => 'piece',
            'serving_name' => __('app.quick_entry_serving'),
            'quantity' => 1,
            'amount' => 1,
            'calories' => round((float) $validated['calories'], 2),
            'protein' => round((float) ($validated['protein'] ?? 0), 2),
            'carbohydrates' => round(
                (float) ($validated['carbohydrates'] ?? 0),
                2
            ),
            'fat' => round((float) ($validated['fat'] ?? 0), 2),
            'position' => (int) $day->entries()
                ->where('meal', $validated['meal'])
                ->max('position') + 1,
        ]);

        return redirect()
            ->route('diary.show', ['date' => $validated['date']])
            ->with('success', __('app.quick_entry_added'));
    }

    public function destroy(Request $request, DiaryEntry $diaryEntry): RedirectResponse
    {
        abort_unless($diaryEntry->day->user_id === $request->user()->id, 403);
        $diaryEntry->delete();

        return back()->with('success', __('app.entry_removed'));
    }
}
