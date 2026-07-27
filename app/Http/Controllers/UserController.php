<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => [
                'nullable',
                'string',
                'max:32',
                'regex:/^[a-zA-Z0-9_]*$/',
            ],
            'tab' => ['nullable', 'in:friends,requests'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $tab = $validated['tab'] ?? 'friends';
        $currentUser = $request->user();
        $friendships = Friendship::query()
            ->where(function ($query) use ($currentUser) {
                $query
                    ->where('user_id', $currentUser->id)
                    ->orWhere('friend_id', $currentUser->id);
            })
            ->with([
                'user:id,name,username',
                'friend:id,name,username',
            ])
            ->latest()
            ->get();
        $friendshipsByUser = $friendships->keyBy(
            fn (Friendship $friendship) => $friendship->user_id === $currentUser->id
                ? $friendship->friend_id
                : $friendship->user_id
        );
        $friends = $friendships
            ->where('status', Friendship::STATUS_ACCEPTED)
            ->map(fn (Friendship $friendship) => [
                ...$friendship->otherUser($currentUser)
                    ->only(['id', 'name', 'username']),
                ...$this->friendshipPayload($friendship, $currentUser),
            ])
            ->sortBy('username')
            ->values();
        $requests = $friendships
            ->where('status', Friendship::STATUS_PENDING)
            ->map(fn (Friendship $friendship) => [
                ...$friendship->otherUser($currentUser)
                    ->only(['id', 'name', 'username']),
                ...$this->friendshipPayload($friendship, $currentUser),
            ])
            ->values();
        $matchedUser = $search === ''
            ? null
            : User::query()
                ->where('username', Str::lower($search))
                ->first(['id', 'name', 'username']);
        $searchResult = $matchedUser
            ? [
                ...$matchedUser->only(['id', 'name', 'username']),
                ...($matchedUser->is($currentUser)
                    ? [
                        'friendship_id' => null,
                        'friendship_state' => 'self',
                    ]
                    : $this->friendshipPayload(
                        $friendshipsByUser->get($matchedUser->id),
                        $currentUser
                    )),
            ]
            : null;

        return Inertia::render('users/index', [
            'friends' => $friends,
            'requests' => $requests,
            'searchResult' => $searchResult,
            'filters' => [
                'search' => $search,
                'tab' => $tab,
            ],
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        $currentUser = $request->user();
        $friendship = $currentUser->id === $user->id
            ? null
            : $currentUser->friendshipWith($user);
        $canViewRecipes = $currentUser->id === $user->id
            || $friendship?->status === Friendship::STATUS_ACCEPTED;
        $favouriteIds = DB::table('food_favourites')
            ->where('user_id', $currentUser->id)
            ->pluck('food_id');
        $recipes = $canViewRecipes
            ? $user->recipes()
                ->with('ingredients.food.translation')
                ->latest()
                ->get()
                ->map(fn (Recipe $recipe) => $this->recipePayload(
                    $recipe,
                    $favouriteIds->contains($recipe->food_id)
                ))
            : collect();

        return Inertia::render('users/show', [
            'profileUser' => $user->only(['id', 'name', 'username']),
            'friendship' => $currentUser->id === $user->id
                ? ['friendship_state' => 'self', 'friendship_id' => null]
                : $this->friendshipPayload($friendship, $currentUser),
            'canViewRecipes' => $canViewRecipes,
            'recipes' => $recipes,
        ]);
    }

    /**
     * @return array{friendship_id: int|null, friendship_state: string}
     */
    private function friendshipPayload(
        ?Friendship $friendship,
        User $currentUser
    ): array {
        if (! $friendship) {
            return [
                'friendship_id' => null,
                'friendship_state' => 'none',
            ];
        }

        if ($friendship->status === Friendship::STATUS_ACCEPTED) {
            return [
                'friendship_id' => $friendship->id,
                'friendship_state' => 'friends',
            ];
        }

        return [
            'friendship_id' => $friendship->id,
            'friendship_state' => $friendship->requested_by === $currentUser->id
                ? 'outgoing'
                : 'incoming',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function recipePayload(Recipe $recipe, bool $isFavourite): array
    {
        $weight = max($recipe->cooked_weight, 0.001);

        return [
            ...$recipe->only([
                'id',
                'food_id',
                'name',
                'cooked_weight',
            ]),
            'calories' => round($recipe->total_calories / $weight * 100, 2),
            'protein' => round($recipe->total_protein / $weight * 100, 2),
            'carbohydrates' => round(
                $recipe->total_carbohydrates / $weight * 100,
                2
            ),
            'fat' => round($recipe->total_fat / $weight * 100, 2),
            'fibre' => round($recipe->total_fibre / $weight * 100, 2),
            'is_favourite' => $isFavourite,
            'ingredients' => $recipe->ingredients->map(fn ($ingredient) => [
                'id' => $ingredient->id,
                'name' => $ingredient->food?->localizedName()
                    ?? $ingredient->food_name,
                'amount' => $ingredient->amount,
                'unit' => $ingredient->unit,
            ])->values(),
        ];
    }
}
