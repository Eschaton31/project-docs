<?php

namespace Tests\Feature;

use Tests\TestCase;

class ForceHttpsBehindProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://docs.example.com']);
        $this->withoutVite();
    }

    public function test_proxied_requests_get_https_urls(): void
    {
        $this->get('http://docs.example.com/', ['X-Forwarded-For' => '203.0.113.7'])->assertOk();

        $this->assertSame('https://docs.example.com/build/app.js', asset('build/app.js'));
    }

    public function test_direct_requests_keep_their_scheme_so_headless_chrome_can_load_the_print_view(): void
    {
        $this->get('http://127.0.0.1/render/some-document')->assertOk();

        $this->assertSame('http://127.0.0.1/build/app.js', asset('build/app.js'));
    }
}
