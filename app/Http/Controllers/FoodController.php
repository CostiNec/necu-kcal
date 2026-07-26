<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\User;
use App\Services\FoodSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FoodController extends Controller
{
    public function __construct(private FoodSearch $foodSearch)
    {
    }

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
            'unit_type' => ['nullable', 'in:g,ml,piece'],
            'favourites_only' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $favouritesOnly = (bool) ($validated['favourites_only'] ?? false);

        $paginator = $this->paginatedQuery(
            $request->user(),
            $search,
            $favouritesOnly,
            $validated['unit_type'] ?? null
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

        $food = DB::transaction(function () use ($request, $validated) {
            $food = $request->user()->foods()->create([
                ...collect($validated)->except(['serving_name', 'serving_amount'])->all(),
                'is_public' => false,
            ]);

            $food->servings()->create([
                'name' => $validated['serving_name'],
                'amount' => $validated['serving_amount'],
                'is_default' => true,
            ]);

            return $food;
        });

        return back()->with([
            'success' => __('app.custom_food_created'),
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
        bool $favouritesOnly,
        ?string $unitType = null
    ): Builder {
        return $this->foodSearch
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
            ->with([
                'translation',
                'servings' => fn ($query) => $query
                    ->orderByDesc('is_default')
                    ->orderBy('id'),
            ])
            ->when(
                $favouritesOnly,
                fn (Builder $query) => $query->whereNotNull('favourite.id')
            )
            ->when(
                $unitType,
                fn (Builder $query, string $type) => $query->where(
                    'foods.unit_type',
                    $type
                )
            )
            ->orderBy('foods.name')
            ->orderBy('foods.id');
    }

    /**
     * @return array<string, mixed>
     */
    private function foodPayload(Food $food): array
    {
        $serving = $food->servings->first();

        return [
            ...$food->only([
                'id',
                'brand',
                'barcode',
                'calories',
                'protein',
                'carbohydrates',
                'fat',
                'fibre',
                'unit_type',
            ]),
            'name' => $food->localizedName(),
            'is_custom' => $food->user_id !== null,
            'is_favourite' => (bool) $food->is_favourite,
            'serving' => $serving ? [
                'name' => $serving->translation_key
                    ? __($serving->translation_key)
                    : $serving->name,
                'translation_key' => $serving->translation_key,
                'amount' => $serving->amount,
            ] : null,
        ];
    }
}
