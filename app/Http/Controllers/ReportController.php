<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use App\Models\WeightLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $timezone = $request->user()->profile?->timezone ?? config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $request->validate([
            'range' => ['nullable', Rule::in(['7', '30', '365', 'custom'])],
        ]);

        $range = (string) $request->query('range', '7');

        if ($range === 'custom') {
            $validated = $request->validate([
                'start' => ['required', 'date_format:Y-m-d'],
                'end' => [
                    'required',
                    'date_format:Y-m-d',
                    'after_or_equal:start',
                    'before_or_equal:'.$today->toDateString(),
                ],
            ]);
            $start = CarbonImmutable::parse($validated['start'], $timezone);
            $end = CarbonImmutable::parse($validated['end'], $timezone);

            abort_if(
                $start->diffInDays($end) > 364,
                422,
                'Custom report ranges cannot exceed 365 days.'
            );
        } else {
            $periodDays = (int) $range;
            $legacyEnd = ! $request->has('range')
                ? $request->query('end', $request->query('week'))
                : null;
            $end = $legacyEnd
                ? CarbonImmutable::parse($legacyEnd, $timezone)
                : $today;
            $start = $end->subDays($periodDays - 1);
        }

        $periodDays = (int) $start->diffInDays($end) + 1;
        $days = DiaryDay::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->with('entries.food.translation')
            ->get()
            ->keyBy(fn (DiaryDay $day) => $day->date->toDateString());

        $dailyChart = collect(range(0, $periodDays - 1))->map(function (int $offset) use ($days, $start) {
            $date = $start->addDays($offset);
            $entries = $days->get($date->toDateString())?->entries ?? collect();

            return [
                'date' => $date->toDateString(),
                'day' => $date->format('D'),
                'calories' => round((float) $entries->sum('calories')),
                'protein' => round((float) $entries->sum('protein'), 1),
                'carbohydrates' => round((float) $entries->sum('carbohydrates'), 1),
                'fat' => round((float) $entries->sum('fat'), 1),
                'fibre' => round((float) $entries->sum('fibre'), 1),
            ];
        });

        $loggedDays = $dailyChart->where('calories', '>', 0);
        $average = fn (string $key) => $loggedDays->isEmpty()
            ? 0
            : round((float) $loggedDays->avg($key), $key === 'calories' ? 0 : 1);

        $summarize = function (Collection $points): array {
            $loggedPoints = $points->where('calories', '>', 0);
            $average = fn (string $key, int $precision = 1) => $loggedPoints->isEmpty()
                ? 0
                : round((float) $loggedPoints->avg($key), $precision);

            return [
                'date' => $points->first()['date'],
                'day' => $points->first()['day'],
                'calories' => $average('calories', 0),
                'protein' => $average('protein'),
                'carbohydrates' => $average('carbohydrates'),
                'fat' => $average('fat'),
                'fibre' => $average('fibre'),
            ];
        };

        $chart = match (true) {
            $periodDays > 90 => $dailyChart
                ->groupBy(fn (array $point) => substr($point['date'], 0, 7))
                ->map($summarize)
                ->values(),
            $periodDays > 31 => $dailyChart->chunk(7)->map($summarize)->values(),
            default => $dailyChart,
        };

        $topFoods = $days
            ->flatMap->entries
            ->groupBy(fn ($entry) => $entry->food_id
                ? "food:{$entry->food_id}"
                : "snapshot:{$entry->food_name}")
            ->map(fn ($entries) => [
                'name' => $entries->first()->food?->localizedName()
                    ?? $entries->first()->food_name,
                'times' => $entries->count(),
                'calories' => round((float) $entries->sum('calories')),
            ])
            ->sortByDesc('times')
            ->take(5)
            ->values();
        $weightChart = $request->user()
            ->weightLogs()
            ->whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $end->toDateString())
            ->oldest('date')
            ->get()
            ->map(fn (WeightLog $log) => [
                'date' => $log->date->toDateString(),
                'weight' => $log->weight_kg,
            ]);
        $currentWeight = $request->user()
            ->weightLogs()
            ->whereDate('date', '<=', $end->toDateString())
            ->latest('date')
            ->first();
        $firstWeight = $weightChart->first();

        return Inertia::render('reports/index', [
            'period' => [
                'range' => $range,
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'today' => $today->toDateString(),
                'days' => $periodDays,
            ],
            'chart' => $chart,
            'averages' => [
                'calories' => $average('calories'),
                'protein' => $average('protein'),
                'carbohydrates' => $average('carbohydrates'),
                'fat' => $average('fat'),
                'fibre' => $average('fibre'),
            ],
            'loggedDays' => $loggedDays->count(),
            'topFoods' => $topFoods,
            'targets' => $request->user()->nutritionTarget,
            'weightChart' => $weightChart,
            'weightSummary' => [
                'current' => $currentWeight?->weight_kg,
                'change' => $currentWeight && $firstWeight
                    ? round($currentWeight->weight_kg - $firstWeight['weight'], 2)
                    : null,
                'loggedDays' => $weightChart->count(),
            ],
        ]);
    }
}
