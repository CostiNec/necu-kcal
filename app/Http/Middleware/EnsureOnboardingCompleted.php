<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingCompleted
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->profile?->onboarding_completed_at) {
            return redirect()->route('onboarding.show');
        }

        return $next($request);
    }
}
