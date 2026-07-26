<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/index', [
            'profile' => $request->user()->profile,
            'targets' => $request->user()->nutritionTarget,
        ]);
    }

    public function updateTargets(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'calories' => ['required', 'integer', 'min:800', 'max:10000'],
            'protein' => ['required', 'integer', 'min:0', 'max:1000'],
            'carbohydrates' => ['required', 'integer', 'min:0', 'max:1500'],
            'fat' => ['required', 'integer', 'min:0', 'max:500'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $request->user()->nutritionTarget()->updateOrCreate(
            ['user_id' => $request->user()->id],
            collect($validated)->except('timezone')->all()
        );
        $request->user()->profile()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['timezone' => $validated['timezone'], 'unit_system' => 'metric']
        );

        return back()->with('success', __('app.targets_updated'));
    }

    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::guard('web')->logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('home')
            ->with('success', __('app.account_deleted'));
    }
}
