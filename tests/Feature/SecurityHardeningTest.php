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
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                ->post('/admin/login', [
                'mobile' => '09120000000',
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors();
        }

        $this->post('/admin/login', [
            'mobile' => '09120000000',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }


    public function test_admin_login_also_has_an_ip_wide_limit_across_different_mobile_numbers(): void
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $mobile = '09120000'.str_pad((string)$attempt, 3, '0', STR_PAD_LEFT);
            $this->post('/admin/login', [
                'mobile' => $mobile,
                'password' => 'incorrect-password',
            ])->assertSessionHasErrors();
        }

        $this->post('/admin/login', [
            'mobile' => '09129999999',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_customer_otp_verification_is_rate_limited_per_mobile_and_ip(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withSession(['customer_otp_mobile' => '09120000001'])
                ->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
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


    public function test_customer_otp_send_is_rate_limited_per_mobile_even_when_ip_changes(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
                ->post('/account/send-code', ['mobile' => '09120000002'])
                ->assertSessionHasErrors();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
            ->post('/account/send-code', ['mobile' => '09120000002'])
            ->assertTooManyRequests();
    }

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
