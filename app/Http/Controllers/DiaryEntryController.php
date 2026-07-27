<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use App\Models\DiaryEntry;
use App\Models\Food;
use App\Support\MeasurementUnit;
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
            'unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $food = Food::query()
            ->visibleTo($request->user())
            ->with('translation')
            ->findOrFail($validated['food_id']);
        $measurement = $this->measurement(
            $validated,
            $food->nutrition_basis_unit
        );
        $factor = $measurement['total']
            / max($food->nutrition_basis_amount, 0.001);
        $day = DiaryDay::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $validated['date'],
        ]);

        $day->entries()->create([
            'food_id' => $food->id,
            'meal' => $validated['meal'],
            'food_name' => $food->name,
            'brand' => $food->brand,
            'unit' => $validated['unit'],
            'amount' => $validated['amount'],
            'quantity' => $validated['quantity'],
            'total_grams' => $measurement['basis_unit'] === 'g'
                ? $measurement['total']
                : null,
            'total_milliliters' => $measurement['basis_unit'] === 'ml'
                ? $measurement['total']
                : null,
            'calories' => round($food->calories * $factor, 2),
            'protein' => round(($food->protein ?? 0) * $factor, 2),
            'carbohydrates' => round(($food->carbohydrates ?? 0) * $factor, 2),
            'fat' => round(($food->fat ?? 0) * $factor, 2),
            'fibre' => round(($food->fibre ?? 0) * $factor, 2),
            'position' => (int) $day->entries()->where('meal', $validated['meal'])->max('position') + 1,
        ]);

        return redirect()
            ->route('diary.show', [
                'date' => $validated['date'],
                'focus_meal' => $validated['meal'],
            ])
            ->with('success', __('app.food_added', [
                'food' => $food->localizedName(),
            ]));
    }

    public function update(Request $request, DiaryEntry $diaryEntry): RedirectResponse
    {
        abort_unless($diaryEntry->day->user_id === $request->user()->id, 403);
        $previousTotal = $diaryEntry->total_grams
            ?? $diaryEntry->total_milliliters;
        abort_if($previousTotal === null, 422);

        $validated = $request->validate([
            'unit' => ['required', Rule::enum(MeasurementUnit::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:1000'],
        ]);

        $basisUnit = $diaryEntry->food?->nutrition_basis_unit
            ?? MeasurementUnit::from($diaryEntry->unit)->basisUnit();
        $measurement = $this->measurement($validated, $basisUnit);
        $ratio = $measurement['total'] / max($previousTotal, 0.001);

        $diaryEntry->update([
            'unit' => $validated['unit'],
            'amount' => $validated['amount'],
            'quantity' => $validated['quantity'],
            'total_grams' => $basisUnit === 'g'
                ? $measurement['total']
                : null,
            'total_milliliters' => $basisUnit === 'ml'
                ? $measurement['total']
                : null,
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
            'total_milliliters' => null,
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
    private function measurement(
        array $measurement,
        string $nutritionBasisUnit
    ): array {
        $unit = MeasurementUnit::from($measurement['unit']);

        if (! $unit->isCompatibleWith($nutritionBasisUnit)) {
            throw ValidationException::withMessages([
                'unit' => __('app.incompatible_measurement_unit'),
            ]);
        }

        $total = $unit->toBaseAmount(
            (float) $measurement['amount'] * (float) $measurement['quantity']
        );

        if ($total > 10000000) {
            throw ValidationException::withMessages([
                'amount' => __('app.measurement_too_large'),
            ]);
        }

        return [
            'total' => round($total, 3),
            'basis_unit' => $unit->basisUnit(),
        ];
    }
}
