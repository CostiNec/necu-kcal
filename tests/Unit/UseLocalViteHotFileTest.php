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
    private string|false $originalHotFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalHotFile = is_file(public_path('hot'))
            ? file_get_contents(public_path('hot'))
            : false;
    }

    protected function tearDown(): void
    {
        if ($this->originalHotFile === false) {
            @unlink(public_path('hot'));
        } else {
            file_put_contents(public_path('hot'), $this->originalHotFile);
        }

        parent::tearDown();
    }

    #[DataProvider('localHosts')]
    public function test_local_requests_use_the_vite_development_server(
        string $url
    ): void {
        file_put_contents(public_path('hot'), 'http://127.0.0.1:5173');

        $vite = $this->app->make(Vite::class);
        $middleware = new UseLocalViteHotFile($vite, fn () => true);

        $middleware->handle(
            Request::create($url),
            fn () => new Response
        );

        $this->assertSame(public_path('hot'), $vite->hotFile());
    }

    public function test_local_requests_use_compiled_assets_when_the_vite_hot_file_is_stale(): void
    {
        file_put_contents(public_path('hot'), 'http://127.0.0.1:1');

        $vite = $this->app->make(Vite::class);
        $middleware = new UseLocalViteHotFile($vite, fn () => false);

        $middleware->handle(
            Request::create('http://127.0.0.1'),
            fn () => new Response
        );

        $this->assertSame(
            storage_path('framework/vite-external.hot'),
            $vite->hotFile()
        );
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
