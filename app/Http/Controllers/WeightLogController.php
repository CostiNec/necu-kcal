<?php

namespace App\Http\Controllers;

use App\Models\WeightLog;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class WeightLogController extends Controller
{
    public function index(Request $request): Response
    {
        $timezone = $request->user()->profile?->timezone ?? config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $query = $request->user()->weightLogs();
        $entries = (clone $query)
            ->latest('date')
            ->limit(100)
            ->get();
        $trendStart = CarbonImmutable::parse($today, $timezone)->subDays(89);
        $trend = (clone $query)
            ->whereBetween('date', [$trendStart->toDateString(), $today])
            ->oldest('date')
            ->get()
            ->map(fn (WeightLog $log) => [
                'date' => $log->date->toDateString(),
                'weight' => $log->weight_kg,
            ]);
        $latest = $entries->first();
        $trendFirst = $trend->first();

        return Inertia::render('weight/index', [
            'today' => $today,
            'entries' => $entries->map(fn (WeightLog $log) => [
                'id' => $log->id,
                'date' => $log->date->toDateString(),
                'weight' => $log->weight_kg,
                'note' => $log->note,
            ]),
            'trend' => $trend,
            'summary' => [
                'current' => $latest?->weight_kg,
                'change' => $latest && $trendFirst
                    ? round($latest->weight_kg - $trendFirst['weight'], 2)
                    : null,
                'loggedDays' => $trend->count(),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $request->user()->weightLogs()->updateOrCreate(
            ['date' => $validated['date']],
            [
                'weight_kg' => $validated['weight'],
                'note' => $validated['note'] ?? null,
            ]
        );

        return back()->with('success', __('app.weight_saved'));
    }

    public function update(Request $request, WeightLog $weightLog): RedirectResponse
    {
        $this->ensureOwnedByUser($request, $weightLog);
        $validated = $this->validated($request, $weightLog);

        $weightLog->update([
            'date' => $validated['date'],
            'weight_kg' => $validated['weight'],
            'note' => $validated['note'] ?? null,
        ]);

        return back()->with('success', __('app.weight_updated'));
    }

    public function destroy(Request $request, WeightLog $weightLog): RedirectResponse
    {
        $this->ensureOwnedByUser($request, $weightLog);
        $weightLog->delete();

        return back()->with('success', __('app.weight_removed'));
    }

    /**
     * @return array{date: string, weight: float|int|string, note?: string|null}
     */
    private function validated(Request $request, ?WeightLog $weightLog = null): array
    {
        $timezone = $request->user()->profile?->timezone ?? config('app.timezone');
        $today = CarbonImmutable::now($timezone)->toDateString();
        $dateRules = [
            'required',
            'date_format:Y-m-d',
            'before_or_equal:'.$today,
        ];

        if ($weightLog) {
            $dateRules[] = Rule::unique('weight_logs', 'date')
                ->where('user_id', $request->user()->id)
                ->ignore($weightLog->id);
        }

        return $request->validate([
            'date' => $dateRules,
            'weight' => ['required', 'numeric', 'min:20', 'max:500'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
    }

    private function ensureOwnedByUser(Request $request, WeightLog $weightLog): void
    {
        abort_unless($weightLog->user_id === $request->user()->id, 404);
    }
}
