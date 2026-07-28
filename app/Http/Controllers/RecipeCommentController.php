<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Models\RecipeComment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RecipeCommentController extends Controller
{
    public function store(Request $request, Recipe $recipe): RedirectResponse
    {
        abort_unless($recipe->isVisibleTo($request->user()), 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $recipe->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);

        return back()->with('success', __('app.recipe_comment_added'));
    }

    public function update(
        Request $request,
        Recipe $recipe,
        RecipeComment $comment
    ): RedirectResponse {
        abort_unless($recipe->isVisibleTo($request->user()), 404);
        abort_unless($comment->recipe_id === $recipe->id, 404);
        abort_unless($comment->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'body' => ['present', 'nullable', 'string', 'max:2000'],
        ]);
        $body = trim((string) ($validated['body'] ?? ''));

        if ($body === '') {
            $comment->delete();

            return back()->with(
                'success',
                __('app.recipe_comment_removed')
            );
        }

        $comment->update(['body' => $body]);

        return back()->with('success', __('app.recipe_comment_updated'));
    }
}
