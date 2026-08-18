<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UseLocalViteHotFile
{
    public function __construct(
        private Vite $vite,
        private ?Closure $reachabilityCheck = null
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $localHotFile = public_path('hot');

        $this->vite->useHotFile(
            $this->isLocalHost($request->getHost())
                && $this->viteServerIsReachable($localHotFile)
                ? $localHotFile
                : storage_path('framework/vite-external.hot')
        );

        return $next($request);
    }

    private function viteServerIsReachable(string $hotFile): bool
    {
        if (! is_file($hotFile)) {
            return false;
        }

        $url = trim((string) file_get_contents($hotFile));
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        $endpoint = "tcp://{$parts['host']}:{$port}";

        if ($this->reachabilityCheck !== null) {
            return (bool) ($this->reachabilityCheck)($endpoint);
        }

        $socket = @stream_socket_client(
            $endpoint,
            $errorCode,
            $errorMessage,
            0.05,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
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
