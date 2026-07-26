<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DiaryController extends Controller
{
    public function today(Request $request)
    {
        $timezone = $request->user()->profile?->timezone ?? config('app.timezone');

        return redirect()->route('diary.show', [
            'date' => CarbonImmutable::now($timezone)->toDateString(),
        ]);
    }

    public function show(Request $request, string $date): Response
    {
        abort_unless(
            preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
            && CarbonImmutable::createFromFormat('Y-m-d', $date) !== false,
            404
        );

        $user = $request->user();
        $timezone = $user->profile?->timezone ?? config('app.timezone');
        $selectedDate = CarbonImmutable::parse($date, $timezone);
        $day = DiaryDay::query()
            ->where('user_id', $user->id)
            ->whereDate('date', $selectedDate)
            ->with([
                'entries' => fn ($query) => $query
                    ->with('food.translation')
                    ->orderBy('position')
                    ->orderBy('id'),
            ])
            ->first();

        $entries = $day?->entries ?? new Collection;
        $totals = [
            'calories' => round((float) $entries->sum('calories'), 1),
            'protein' => round((float) $entries->sum('protein'), 1),
            'carbohydrates' => round((float) $entries->sum('carbohydrates'), 1),
            'fat' => round((float) $entries->sum('fat'), 1),
            'fibre' => round((float) $entries->sum('fibre'), 1),
        ];

        return Inertia::render('diary/show', [
            'date' => $selectedDate->toDateString(),
            'isToday' => $selectedDate->isSameDay(CarbonImmutable::now($timezone)),
            'previousDate' => $selectedDate->subDay()->toDateString(),
            'nextDate' => $selectedDate->addDay()->toDateString(),
            'entries' => $entries->values()->map(fn ($entry) => [
                ...$entry->toArray(),
                'food_name' => $entry->food?->localizedName()
                    ?? $entry->food_name,
                'serving_name' => $entry->serving_translation_key
                    ? __($entry->serving_translation_key)
                    : $entry->serving_name,
            ]),
            'totals' => $totals,
            'targets' => $user->nutritionTarget,
            'notes' => $day?->notes,
        ]);
    }

    public function updateNotes(Request $request, string $date)
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:2000']]);
        $day = DiaryDay::firstOrCreate([
            'user_id' => $request->user()->id,
            'date' => $date,
        ]);
        $day->update(['notes' => $validated['notes']]);

        return back()->with('success', __('app.daily_note_saved'));
    }
}
