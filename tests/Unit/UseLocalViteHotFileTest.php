<?php

namespace Tests\Unit;

use App\Http\Middleware\UseLocalViteHotFile;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class UseLocalViteHotFileTest extends TestCase
{
    #[DataProvider('localHosts')]
    public function test_local_requests_use_the_vite_development_server(
        string $url
    ): void {
        $vite = $this->app->make(Vite::class);
        $middleware = new UseLocalViteHotFile($vite);

        $middleware->handle(
            Request::create($url),
            fn () => new Response
        );

        $this->assertSame(public_path('hot'), $vite->hotFile());
    }

    public function test_external_requests_use_compiled_assets(): void
    {
        $vite = $this->app->make(Vite::class);
        $middleware = new UseLocalViteHotFile($vite);

        $middleware->handle(
            Request::create('https://example.trycloudflare.com'),
            fn () => new Response
        );

        $this->assertSame(
            storage_path('framework/vite-external.hot'),
            $vite->hotFile()
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function localHosts(): array
    {
        return [
            'localhost' => ['http://localhost'],
            'IPv4 loopback' => ['http://127.0.0.1'],
            'IPv6 loopback' => ['http://[::1]'],
            'local test domain' => ['http://necutrack.test'],
        ];
    }
}
