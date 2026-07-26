<?php

namespace App\Http\Controllers;

use App\Models\Food;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FoodController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $localizedFoodKeys = collect(__('foods'))
            ->filter(fn (string $name) => str_contains(
                mb_strtolower($name),
                mb_strtolower($search)
            ))
            ->keys()
            ->map(fn (string $key) => "foods.{$key}")
            ->values();
        $favouriteIds = DB::table('food_favourites')
            ->where('user_id', $user->id)
            ->pluck('food_id');

        $foods = Food::query()
            ->visibleTo($user)
            ->with(['servings' => fn ($query) => $query->orderByDesc('is_default')->orderBy('id')])
            ->when($search !== '', function ($query) use ($localizedFoodKeys, $search) {
                $query->where(function ($builder) use ($localizedFoodKeys, $search) {
                    $builder
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('brand', 'like', "%{$search}%")
                        ->orWhere('barcode', $search)
                        ->when(
                            $localizedFoodKeys->isNotEmpty(),
                            fn ($localizedQuery) => $localizedQuery->orWhereIn(
                                'translation_key',
                                $localizedFoodKeys
                            )
                        );
                });
            })
            ->orderByRaw(
                $favouriteIds->isNotEmpty()
                    ? 'CASE WHEN id IN ('.implode(',', $favouriteIds->map(fn ($id) => (int) $id)->all()).') THEN 0 ELSE 1 END'
                    : '1'
            )
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(function (Food $food) use ($favouriteIds) {
                $serving = $food->servings->first();

                return [
                    ...$food->only([
                        'id', 'brand', 'barcode', 'calories', 'protein',
                        'carbohydrates', 'fat', 'fibre', 'unit_type', 'translation_key',
                    ]),
                    'name' => $food->translation_key
                        ? __($food->translation_key)
                        : $food->name,
                    'is_custom' => $food->user_id !== null,
                    'is_favourite' => $favouriteIds->contains($food->id),
                    'serving' => $serving ? [
                        'name' => $serving->translation_key
                            ? __($serving->translation_key)
                            : $serving->name,
                        'translation_key' => $serving->translation_key,
                        'amount' => $serving->amount,
                    ] : null,
                ];
            });

        return Inertia::render('foods/index', [
            'foods' => $foods,
            'filters' => ['search' => $search],
            'context' => [
                'date' => $request->query('date'),
                'meal' => $request->query('meal', 'snacks'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'calories' => ['required', 'numeric', 'min:0', 'max:10000'],
            'protein' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'carbohydrates' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fat' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fibre' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'unit_type' => ['required', 'in:g,ml,piece'],
            'serving_name' => ['required', 'string', 'max:100'],
            'serving_amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
        ]);

        DB::transaction(function () use ($request, $validated) {
            $food = $request->user()->foods()->create([
                ...collect($validated)->except(['serving_name', 'serving_amount'])->all(),
                'is_public' => false,
            ]);

            $food->servings()->create([
                'name' => $validated['serving_name'],
                'amount' => $validated['serving_amount'],
                'is_default' => true,
            ]);
        });

        return back()->with('success', __('app.custom_food_created'));
    }

    public function destroy(Request $request, Food $food): RedirectResponse
    {
        abort_unless($food->user_id === $request->user()->id, 403);
        $food->delete();

        return back()->with('success', __('app.food_removed'));
    }
}
