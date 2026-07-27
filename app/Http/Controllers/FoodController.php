<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\User;
use App\Services\FoodSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FoodController extends Controller
{
    public function __construct(private FoodSearch $foodSearch) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = trim((string) $request->query('search', ''));
        $paginator = $this->paginatedQuery(
            $user,
            $search,
            $search === ''
        )->cursorPaginate(20);

        return Inertia::render('foods/index', [
            'foods' => collect($paginator->items())
                ->map(fn (Food $food) => $this->foodPayload($food)),
            'filters' => ['search' => $search],
            'pagination' => [
                'next_cursor' => $paginator->nextCursor()?->encode(),
            ],
            'context' => [
                'date' => $request->query('date'),
                'meal' => $request->query('meal', 'snacks'),
            ],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'favourites_only' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $favouritesOnly = (bool) ($validated['favourites_only'] ?? false);

        $paginator = $this->paginatedQuery(
            $request->user(),
            $search,
            $favouritesOnly
        )->cursorPaginate(20);

        return response()->json([
            'foods' => collect($paginator->items())
                ->map(fn (Food $food) => $this->foodPayload($food)),
            'next_cursor' => $paginator->nextCursor()?->encode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                'regex:/^\d{6,18}$/',
                Rule::unique('foods', 'barcode')->where(
                    fn ($query) => $query
                        ->whereNull('canonical_food_id')
                        ->where('is_active', true)
                ),
            ],
            'calories' => ['required', 'numeric', 'min:0', 'max:10000'],
            'nutrition_basis_amount' => [
                'sometimes',
                'numeric',
                'min:0.01',
                'max:1000000',
            ],
            'nutrition_basis_unit' => [
                'sometimes',
                Rule::in(['g', 'ml']),
            ],
            'protein' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'carbohydrates' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fat' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'fibre' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ]);
        $hasBarcode = isset($validated['barcode'])
            && trim($validated['barcode']) !== '';

        $food = Food::create([
            ...$validated,
            'user_id' => $hasBarcode ? null : $request->user()->id,
            'food_type' => $hasBarcode ? 'product' : 'custom',
            'search_priority' => $hasBarcode ? 2 : 1,
            'nutrition_basis_amount' => $validated['nutrition_basis_amount']
                ?? 100,
            'nutrition_basis_unit' => $validated['nutrition_basis_unit']
                ?? 'g',
            'is_public' => $hasBarcode,
        ]);

        return back()->with([
            'success' => __(
                $hasBarcode
                    ? 'app.shared_product_created'
                    : 'app.custom_food_created'
            ),
            'created_food_id' => $food->id,
        ]);
    }

    public function destroy(Request $request, Food $food): RedirectResponse
    {
        abort_unless($food->user_id === $request->user()->id, 403);
        $food->delete();

        return back()->with('success', __('app.food_removed'));
    }

    private function paginatedQuery(
        User $user,
        string $search,
        bool $favouritesOnly
    ): Builder {
        $query = $this->foodSearch
            ->query($user, $search)
            ->leftJoin('food_favourites as favourite', function ($join) use ($user) {
                $join
                    ->on('favourite.food_id', '=', 'foods.id')
                    ->where('favourite.user_id', '=', $user->id);
            })
            ->select('foods.*')
            ->selectRaw(
                'CASE WHEN favourite.id IS NULL THEN 0 ELSE 1 END AS is_favourite'
            )
            ->with('translation')
            ->when(
                $favouritesOnly,
                fn (Builder $query) => $query->whereNotNull('favourite.id')
            );

        return $this->foodSearch->order($query, $search);
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
                'barcode',
                'calories',
                'nutrition_basis_amount',
                'nutrition_basis_unit',
                'protein',
                'carbohydrates',
                'fat',
                'fibre',
            ]),
            'name' => $food->localizedName(),
            'is_custom' => $food->food_type === 'custom',
            'is_favourite' => (bool) $food->is_favourite,
        ];
    }
}
