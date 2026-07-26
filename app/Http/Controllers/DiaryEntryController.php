<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\Food;
use App\Support\MassUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DiaryEntryController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'food_id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'meal' => ['required', 'in:breakfast,lunch,dinner,snacks'],
            'unit' => ['required', Rule::enum(MassUnit::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
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

        $totalGrams = $this->totalGrams($validated);
        $factor = $totalGrams / 100;

        $day->entries()->create([
            'food_id' => $food->id,
            'meal' => $validated['meal'],
            'food_name' => $food->name,
            'brand' => $food->brand,
            'unit' => $validated['unit'],
            'amount' => $validated['amount'],
            'quantity' => $validated['quantity'],
            'total_grams' => $totalGrams,
            'calories' => round($food->calories * $factor, 2),
            'protein' => round(($food->protein ?? 0) * $factor, 2),
            'carbohydrates' => round(($food->carbohydrates ?? 0) * $factor, 2),
            'fat' => round(($food->fat ?? 0) * $factor, 2),
            'fibre' => round(($food->fibre ?? 0) * $factor, 2),
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
        abort_if($diaryEntry->total_grams === null, 422);

        $validated = $request->validate([
            'unit' => ['required', Rule::enum(MassUnit::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $totalGrams = $this->totalGrams($validated);
        $ratio = $totalGrams / max($diaryEntry->total_grams, 0.001);

        $diaryEntry->update([
            'unit' => $validated['unit'],
            'amount' => $validated['amount'],
            'quantity' => $validated['quantity'],
            'total_grams' => $totalGrams,
            'calories' => round($diaryEntry->calories * $ratio, 2),
            'protein' => round($diaryEntry->protein * $ratio, 2),
            'carbohydrates' => round($diaryEntry->carbohydrates * $ratio, 2),
            'fat' => round($diaryEntry->fat * $ratio, 2),
            'fibre' => round($diaryEntry->fibre * $ratio, 2),
        ]);

        return back()->with('success', __('app.amount_updated'));
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
            'fibre' => ['nullable', 'numeric', 'min:0', 'max:10000'],
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
            'unit' => 'g',
            'quantity' => 1,
            'amount' => 1,
            'total_grams' => null,
            'calories' => round((float) $validated['calories'], 2),
            'protein' => round((float) ($validated['protein'] ?? 0), 2),
            'carbohydrates' => round(
                (float) ($validated['carbohydrates'] ?? 0),
                2
            ),
            'fat' => round((float) ($validated['fat'] ?? 0), 2),
            'fibre' => round((float) ($validated['fibre'] ?? 0), 2),
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

    /**
     * @param  array{unit: string, amount: float|int|string, quantity: float|int|string}  $measurement
     */
    private function totalGrams(array $measurement): float
    {
        $total = MassUnit::from($measurement['unit'])->toGrams(
            (float) $measurement['amount'] * (float) $measurement['quantity']
        );

        if ($total > 10000000) {
            throw ValidationException::withMessages([
                'amount' => __('app.weight_too_large'),
            ]);
        }

        return round($total, 3);
    }
}
