<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_headers_are_added_to_storefront_responses(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    public function test_customer_and_admin_pages_are_not_cached(): void
    {
        foreach (['/admin/login', '/cart', '/checkout', '/order/track'] as $path) {
            $this->get($path)
                ->assertHeader('Cache-Control', 'no-store, private, max-age=0')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0');
        }
    }

    public function test_hsts_is_only_added_to_secure_requests(): void
    {
        $this->get('/')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('/', ['HTTPS' => 'on'])
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
