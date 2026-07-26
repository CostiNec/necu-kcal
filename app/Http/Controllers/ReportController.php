<?php

namespace App\Http\Controllers;

use App\Models\DiaryDay;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $timezone = $request->user()->profile?->timezone ?? config('app.timezone');
        $anchor = CarbonImmutable::parse(
            $request->query('week', CarbonImmutable::now($timezone)->toDateString()),
            $timezone
        );
        $start = $anchor->startOfWeek();
        $end = $start->addDays(6);
        $days = DiaryDay::query()
            ->where('user_id', $request->user()->id)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->with('entries.food.translation')
            ->get()
            ->keyBy(fn (DiaryDay $day) => $day->date->toDateString());

        $chart = collect(range(0, 6))->map(function (int $offset) use ($days, $start) {
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

        $loggedDays = $chart->where('calories', '>', 0);
        $average = fn (string $key) => $loggedDays->isEmpty()
            ? 0
            : round((float) $loggedDays->avg($key), $key === 'calories' ? 0 : 1);

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

        return Inertia::render('reports/index', [
            'week' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'previous' => $start->subWeek()->toDateString(),
                'next' => $start->addWeek()->toDateString(),
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
        ]);
    }
}
