<?php

namespace App\Http\Controllers;

use App\Models\Food;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FavouriteFoodController extends Controller
{
    public function toggle(Request $request, Food $food): RedirectResponse
    {
        abort_unless(
            $food->is_public || $food->user_id === $request->user()->id,
            403
        );

        $query = DB::table('food_favourites')->where([
            'user_id' => $request->user()->id,
            'food_id' => $food->id,
        ]);

        if ($query->exists()) {
            $query->delete();
        } else {
            DB::table('food_favourites')->insert([
                'user_id' => $request->user()->id,
                'food_id' => $food->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back();
    }
}
