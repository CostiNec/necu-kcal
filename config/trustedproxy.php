<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxies such as Cloudflare terminate HTTPS before forwarding the
    | request to Laravel over HTTP. Trusting the proxy allows Laravel to use
    | X-Forwarded-Proto when generating asset and application URLs.
    |
    */
    'proxies' => env('TRUSTED_PROXIES'),
];
