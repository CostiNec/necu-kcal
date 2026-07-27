<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseLocalViteHotFile
{
    public function __construct(private Vite $vite) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->vite->useHotFile(
            $this->isLocalHost($request->getHost())
                ? public_path('hot')
                : storage_path('framework/vite-external.hot')
        );

        return $next($request);
    }

    private function isLocalHost(string $host): bool
    {
        $host = strtolower($host);

        return in_array(
            $host,
            ['localhost', '127.0.0.1', '::1', '[::1]'],
            true
        ) || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }
}
