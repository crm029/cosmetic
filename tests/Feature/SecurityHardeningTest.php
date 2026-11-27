<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_account_responses_are_never_cached(): void
    {
        $this->get('/account')
            ->assertRedirect('/account/login')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')
            ->assertHeader('X-Frame-Options', 'DENY');

        $this->get('/account/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_admin_login_is_rate_limited_per_mobile_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', [
                'mobile' => '09120000000',
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors();
        }

        $this->post('/admin/login', [
            'mobile' => '09120000000',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_customer_otp_verification_is_rate_limited_per_mobile_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession(['customer_otp_mobile' => '09120000001'])
                ->post('/account/verify', [
                    'mobile' => '09120000001',
                    'code' => '123456',
                ])->assertSessionHasErrors();
        }

        $this->withSession(['customer_otp_mobile' => '09120000001'])
            ->post('/account/verify', [
                'mobile' => '09120000001',
                'code' => '123456',
            ])->assertTooManyRequests();
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
