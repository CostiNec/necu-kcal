<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeReaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RecipeReactionController extends Controller
{
    public function store(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_unless($recipe->isVisibleTo($request->user()), 404);
        abort_if($recipe->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'reaction' => [
                'required',
                Rule::in([
                    RecipeReaction::LIKE,
                    RecipeReaction::DISLIKE,
                ]),
            ],
        ]);
        $reaction = $recipe->reactions()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($reaction?->reaction === $validated['reaction']) {
            $reaction->delete();
        } else {
            $recipe->reactions()->updateOrCreate(
                ['user_id' => $request->user()->id],
                ['reaction' => $validated['reaction']]
            );
        }

        return back();
    }
}
