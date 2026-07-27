<?php

namespace Tests\Feature;

use Tests\TestCase;

class SessionConfigurationTest extends TestCase
{
    public function test_user_sessions_and_remember_cookies_last_six_months(): void
    {
        $this->assertSame(259200, config('session.lifetime'));
        $this->assertFalse(config('session.expire_on_close'));
        $this->assertSame(259200, config('auth.guards.web.remember'));
    }
}
