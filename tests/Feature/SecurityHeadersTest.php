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
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Content-Security-Policy');
    }


    public function test_content_security_policy_uses_a_per_response_script_nonce(): void
    {
        $response = $this->get('/');

        $policy = $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self' 'nonce-", $policy);
        $this->assertDoesNotMatchRegularExpression("/script-src[^;]*'unsafe-inline'/", $policy);
        $this->assertMatchesRegularExpression('/nonce="[^"]+"/', $response->getContent());
    }

    public function test_content_security_policy_limits_images_and_network_requests_to_same_origin(): void
    {
        $policy = $this->get('/')->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("img-src 'self' data: blob:", $policy);
        $this->assertStringContainsString("connect-src 'self'", $policy);
        $this->assertDoesNotMatchRegularExpression("/(?:img-src|connect-src)[^;]*https:/", $policy);
    }

    public function test_customer_and_admin_pages_are_not_cached(): void
    {
        foreach (['/admin/login', '/cart', '/checkout', '/order/track'] as $path) {
            $this->get($path)
                ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
                ->assertHeader('Pragma', 'no-cache')
                ->assertHeader('Expires', '0');
        }
    }

    public function test_hsts_is_only_added_to_secure_requests(): void
    {
        $this->get('/')
            ->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
