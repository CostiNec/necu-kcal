<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = (string) $request->session()->get('locale', config('app.locale'));
        $supportedLocales = array_keys(config('locales.supported', []));

        if (! in_array($locale, $supportedLocales, true)) {
            $locale = (string) config('app.fallback_locale', 'en');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
