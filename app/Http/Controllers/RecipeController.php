<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\Recipe;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RecipeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $recipes = $user->recipes()
            ->with('ingredients.food.translation')
            ->latest()
            ->get()
            ->map(fn (Recipe $recipe) => [
                ...$recipe->only([
                    'id',
                    'food_id',
                    'name',
                    'cooked_weight',
                    'total_calories',
                    'total_protein',
                    'total_carbohydrates',
                    'total_fat',
                    'total_fibre',
                ]),
                'calories' => $this->perHundred(
                    $recipe->total_calories,
                    $recipe->cooked_weight
                ),
                'protein' => $this->perHundred(
                    $recipe->total_protein,
                    $recipe->cooked_weight
                ),
                'carbohydrates' => $this->perHundred(
                    $recipe->total_carbohydrates,
                    $recipe->cooked_weight
                ),
                'fat' => $this->perHundred(
                    $recipe->total_fat,
                    $recipe->cooked_weight
                ),
                'fibre' => $this->perHundred(
                    $recipe->total_fibre,
                    $recipe->cooked_weight
                ),
                'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                    'id' => $ingredient->id,
                    'food_id' => $ingredient->food_id,
                    'name' => $ingredient->food?->localizedName()
                        ?? $ingredient->food_name,
                    'amount' => $ingredient->amount,
                    'unit' => $ingredient->unit,
                ])->values(),
            ]);

        return Inertia::render('recipes/index', [
            'recipes' => $recipes,
        ]);
    }

    public function create(Request $request): Response
    {
        $createdFood = $this->createdFood($request);

        return Inertia::render('recipes/create', [
            'createdFood' => $createdFood
                ? $this->foodPayload($createdFood)
                : null,
        ]);
    }

    public function edit(Request $request, Recipe $recipe): Response
    {
        $this->authorizeRecipe($request, $recipe);
        $recipe->load('ingredients.food.translation');
        $createdFood = $this->createdFood($request);

        return Inertia::render('recipes/edit', [
            'recipe' => [
                'id' => $recipe->id,
                'name' => $recipe->name,
                'cooked_weight' => $recipe->cooked_weight,
                'ingredients' => $recipe->ingredients->map(
                    fn ($ingredient) => [
                        'food_id' => $ingredient->food_id,
                        'amount' => $ingredient->amount,
                        'food' => $ingredient->food
                            ? $this->foodPayload($ingredient->food)
                            : null,
                    ]
                )->values(),
            ],
            'createdFood' => $createdFood
                ? $this->foodPayload($createdFood)
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedRecipe($request);
        $foods = $this->ingredientFoods($request, $validated);

        $totals = $this->calculateTotals(
            collect($validated['ingredients']),
            $foods
        );
        $cookedWeight = (float) $validated['cooked_weight'];
        $nutrition = collect($totals)
            ->map(fn (float $total) => $this->perHundred($total, $cookedWeight));

        DB::transaction(function () use (
            $request,
            $validated,
            $foods,
            $totals,
            $nutrition,
            $cookedWeight
        ) {
            $food = $request->user()->foods()->create([
                'name' => $validated['name'],
                'calories' => $nutrition['calories'],
                'protein' => $nutrition['protein'],
                'carbohydrates' => $nutrition['carbohydrates'],
                'fat' => $nutrition['fat'],
                'fibre' => $nutrition['fibre'],
                'nutrition_basis_amount' => 100,
                'nutrition_basis_unit' => 'g',
                'is_public' => false,
            ]);

            $recipe = $request->user()->recipes()->create([
                'food_id' => $food->id,
                'name' => $validated['name'],
                'cooked_weight' => $cookedWeight,
                'total_calories' => $totals['calories'],
                'total_protein' => $totals['protein'],
                'total_carbohydrates' => $totals['carbohydrates'],
                'total_fat' => $totals['fat'],
                'total_fibre' => $totals['fibre'],
            ]);

            $this->replaceIngredients(
                $recipe,
                $validated['ingredients'],
                $foods
            );
        });

        return redirect()
            ->route('recipes.index')
            ->with('success', __('app.recipe_created'));
    }

    public function update(
        Request $request,
        Recipe $recipe
    ): RedirectResponse {
        $this->authorizeRecipe($request, $recipe);
        $validated = $this->validatedRecipe($request);
        $foodIds = collect($validated['ingredients'])->pluck('food_id');

        if ($recipe->food_id && $foodIds->contains($recipe->food_id)) {
            throw ValidationException::withMessages([
                'ingredients' => __('app.recipe_cannot_include_itself'),
            ]);
        }

        $foods = $this->ingredientFoods($request, $validated);
        $totals = $this->calculateTotals(
            collect($validated['ingredients']),
            $foods
        );
        $cookedWeight = (float) $validated['cooked_weight'];
        $nutrition = collect($totals)
            ->map(fn (float $total) => $this->perHundred($total, $cookedWeight));

        DB::transaction(function () use (
            $request,
            $recipe,
            $validated,
            $foods,
            $totals,
            $nutrition,
            $cookedWeight
        ) {
            $food = $recipe->food;

            if (! $food) {
                $food = $request->user()->foods()->create([
                    'name' => $validated['name'],
                    'calories' => $nutrition['calories'],
                    'protein' => $nutrition['protein'],
                    'carbohydrates' => $nutrition['carbohydrates'],
                    'fat' => $nutrition['fat'],
                    'fibre' => $nutrition['fibre'],
                    'nutrition_basis_amount' => 100,
                    'nutrition_basis_unit' => 'g',
                    'is_public' => false,
                ]);
            } else {
                $food->update([
                    'name' => $validated['name'],
                    'calories' => $nutrition['calories'],
                    'protein' => $nutrition['protein'],
                    'carbohydrates' => $nutrition['carbohydrates'],
                    'fat' => $nutrition['fat'],
                    'fibre' => $nutrition['fibre'],
                    'nutrition_basis_amount' => 100,
                    'nutrition_basis_unit' => 'g',
                ]);
            }

            $recipe->update([
                'food_id' => $food->id,
                'name' => $validated['name'],
                'cooked_weight' => $cookedWeight,
                'total_calories' => $totals['calories'],
                'total_protein' => $totals['protein'],
                'total_carbohydrates' => $totals['carbohydrates'],
                'total_fat' => $totals['fat'],
                'total_fibre' => $totals['fibre'],
            ]);

            $this->replaceIngredients(
                $recipe,
                $validated['ingredients'],
                $foods
            );
        });

        return redirect()
            ->route('recipes.index')
            ->with('success', __('app.recipe_updated'));
    }

    public function destroy(Request $request, Recipe $recipe): RedirectResponse
    {
        $this->authorizeRecipe($request, $recipe);

        DB::transaction(function () use ($recipe) {
            $food = $recipe->food;
            $recipe->delete();
            $food?->delete();
        });

        return back()->with('success', __('app.recipe_removed'));
    }

    /**
     * @return array{
     *     name: string,
     *     cooked_weight: float|int|string,
     *     ingredients: array<int, array{food_id: int, amount: float|int|string}>
     * }
     */
    private function validatedRecipe(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cooked_weight' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000',
            ],
            'ingredients' => ['required', 'array', 'min:1', 'max:100'],
            'ingredients.*.food_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'ingredients.*.amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:1000000',
            ],
        ]);
    }

    /**
     * @param  array{ingredients: array<int, array{food_id: int, amount: float|int|string}>}  $validated
     * @return Collection<int, Food>
     */
    private function ingredientFoods(
        Request $request,
        array $validated
    ): Collection {
        $foodIds = collect($validated['ingredients'])->pluck('food_id');
        $foods = Food::query()
            ->visibleTo($request->user())
            ->whereIn('id', $foodIds)
            ->get()
            ->keyBy('id');

        if ($foods->count() !== $foodIds->unique()->count()) {
            throw ValidationException::withMessages([
                'ingredients' => __('app.recipe_food_unavailable'),
            ]);
        }

        return $foods;
    }

    /**
     * @param  array<int, array{food_id: int, amount: float|int|string}>  $ingredients
     * @param  Collection<int, Food>  $foods
     */
    private function replaceIngredients(
        Recipe $recipe,
        array $ingredients,
        Collection $foods
    ): void {
        $recipe->ingredients()->delete();

        foreach ($ingredients as $position => $ingredient) {
            /** @var Food $ingredientFood */
            $ingredientFood = $foods->get($ingredient['food_id']);

            $recipe->ingredients()->create([
                'food_id' => $ingredientFood->id,
                'food_name' => $ingredientFood->name,
                'amount' => $ingredient['amount'],
                'unit' => $ingredientFood->nutrition_basis_unit,
                'calories' => $ingredientFood->calories,
                'protein' => $ingredientFood->protein ?? 0,
                'carbohydrates' => $ingredientFood->carbohydrates ?? 0,
                'fat' => $ingredientFood->fat ?? 0,
                'fibre' => $ingredientFood->fibre ?? 0,
                'position' => $position,
            ]);
        }
    }

    private function createdFood(Request $request): ?Food
    {
        $createdFoodId = $request->session()->get('created_food_id');

        return $createdFoodId
            ? Food::query()
                ->visibleTo($request->user())
                ->with('translation')
                ->find($createdFoodId)
            : null;
    }

    private function authorizeRecipe(Request $request, Recipe $recipe): void
    {
        abort_unless($recipe->user_id === $request->user()->id, 403);
    }

    /**
     * @param  Collection<int, array{food_id: int, amount: float|int|string}>  $ingredients
     * @param  Collection<int, Food>  $foods
     * @return array{calories: float, protein: float, carbohydrates: float, fat: float, fibre: float}
     */
    private function calculateTotals(Collection $ingredients, Collection $foods): array
    {
        $totals = [
            'calories' => 0.0,
            'protein' => 0.0,
            'carbohydrates' => 0.0,
            'fat' => 0.0,
            'fibre' => 0.0,
        ];

        foreach ($ingredients as $ingredient) {
            /** @var Food $food */
            $food = $foods->get($ingredient['food_id']);
            $factor = (float) $ingredient['amount']
                / max($food->nutrition_basis_amount, 0.001);

            foreach (array_keys($totals) as $nutrient) {
                $totals[$nutrient] += (float) ($food->{$nutrient} ?? 0) * $factor;
            }
        }

        return collect($totals)
            ->map(fn (float $value) => round($value, 2))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function foodPayload(Food $food): array
    {
        return [
            ...$food->only([
                'id',
                'brand',
                'calories',
                'nutrition_basis_amount',
                'nutrition_basis_unit',
                'protein',
                'carbohydrates',
                'fat',
                'fibre',
            ]),
            'name' => $food->localizedName(),
        ];
    }

    private function perHundred(float $total, float $cookedWeight): float
    {
        return round(($total / max($cookedWeight, 0.01)) * 100, 2);
    }
}
