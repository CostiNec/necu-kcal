<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('onboarding', [
            'profile' => $request->user()->profile,
            'targets' => $request->user()->nutritionTarget,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'calories' => ['required', 'integer', 'min:800', 'max:10000'],
            'protein' => ['required', 'integer', 'min:0', 'max:1000'],
            'carbohydrates' => ['required', 'integer', 'min:0', 'max:1500'],
            'fat' => ['required', 'integer', 'min:0', 'max:500'],
            'fibre' => ['required', 'integer', 'min:0', 'max:500'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $user = $request->user();
        $user->update(['name' => $validated['name']]);
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'unit_system' => 'metric',
                'timezone' => $validated['timezone'],
                'onboarding_completed_at' => now(),
            ]
        );
        $user->nutritionTarget()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'calories' => $validated['calories'],
                'protein' => $validated['protein'],
                'carbohydrates' => $validated['carbohydrates'],
                'fat' => $validated['fat'],
                'fibre' => $validated['fibre'],
            ]
        );

        return redirect()
            ->route('diary.today')
            ->with('success', __('app.daily_targets_ready'));
    }
}
