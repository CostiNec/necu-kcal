<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => [
                'required',
                'string',
                Rule::in(array_keys(config('locales.supported', []))),
            ],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back();
    }
}
