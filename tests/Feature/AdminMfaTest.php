<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Order;
use App\Services\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminMfaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر',
            'mobile' => '09121112222',
            'password' => Hash::make('A-very-strong-test-password!'),
            'is_admin' => true,
        ]);
    }

    public function test_totp_implementation_matches_rfc_6238_sha1_vector(): void
    {
        $this->assertSame('287082', app(TotpService::class)->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }

    public function test_admin_panel_requires_totp_enrollment_after_password_login(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin')->assertRedirect('/admin/security/setup');
        $this->actingAs($admin)->get('/admin/security/setup')->assertOk()->assertSee('کلید محرمانه');
    }

    public function test_admin_can_enable_totp_and_enter_the_panel(): void
    {
        $admin = $this->admin();
        $totp = app(TotpService::class);
        $secret = $totp->generateSecret();
        $this->actingAs($admin)->withSession(['admin_totp_setup_secret' => $secret])
            ->post('/admin/security/setup', ['code' => $totp->codeAt($secret, now()->timestamp)])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull($admin->fresh()->totp_enabled_at);
        $this->assertNotEmpty($admin->fresh()->totp_secret);
        $this->actingAs($admin->fresh())->get('/admin')->assertOk();
    }

    public function test_active_admin_authenticator_cannot_be_replaced_through_setup_route(): void
    {
        $admin = $this->admin();
        $activeSecret = app(TotpService::class)->generateSecret();
        $pendingSecret = app(TotpService::class)->generateSecret();
        $admin->forceFill(['totp_secret' => $activeSecret, 'totp_enabled_at' => now(), 'totp_last_used_step' => 0])->save();

        $this->actingAs($admin)->withSession(['admin_totp_setup_secret' => $pendingSecret])
            ->post('/admin/security/setup', ['code' => app(TotpService::class)->codeAt($pendingSecret, now()->timestamp)])
            ->assertForbidden();

        $this->assertSame($activeSecret, $admin->fresh()->totp_secret);
    }

    public function test_totp_code_cannot_be_reused_to_verify_a_new_admin_session(): void
    {
        $admin = $this->admin();
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now(), 'totp_last_used_step' => 0])->save();
        $code = app(TotpService::class)->codeAt($secret, now()->timestamp);

        $this->actingAs($admin)->post('/admin/security/verify', ['code' => $code])
            ->assertRedirect(route('admin.dashboard'));

        $this->withSession(['admin_mfa_verified' => false])->post('/admin/security/verify', ['code' => $code])
            ->assertSessionHasErrors('code');
    }

    public function test_admin_password_login_cannot_skip_totp_enrollment(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'mobile' => $admin->mobile,
            'password' => 'A-very-strong-test-password!',
        ])->assertRedirect(route('admin.mfa.setup'));

        $this->get('/admin')->assertRedirect(route('admin.mfa.setup'));
    }

    public function test_totp_secret_is_encrypted_at_rest_and_hidden_from_serialization(): void
    {
        $admin = $this->admin();
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now()])->save();

        $this->assertNotSame($secret, $admin->getRawOriginal('totp_secret'));
        $this->assertSame($secret, $admin->fresh()->totp_secret);
        $this->assertArrayNotHasKey('totp_secret', $admin->fresh()->toArray());
    }

    public function test_mfa_recovery_requires_confirmed_server_console_command(): void
    {
        $admin = $this->admin();
        $secret = app(TotpService::class)->generateSecret();
        $admin->forceFill(['totp_secret' => $secret, 'totp_enabled_at' => now(), 'totp_last_used_step' => 12])->save();

        $this->artisan('admin:mfa-reset', ['mobile' => $admin->mobile])
            ->expectsConfirmation('This removes the current MFA device. Continue only after verifying server ownership?', 'yes')
            ->assertExitCode(0);

        $this->assertNull($admin->fresh()->totp_enabled_at);
        $this->assertNull($admin->fresh()->totp_secret);
        $this->assertSame(0, $admin->fresh()->totp_last_used_step);
    }

    public function test_customer_cannot_view_another_phone_number_order_after_login(): void
    {
        $customer = User::create([
            'name' => 'مشتری مجاز',
            'mobile' => '09123334444',
            'password' => Hash::make('customer-password'),
            'is_admin' => false,
        ]);
        $order = Order::create([
            'order_number' => 'CS-PRIVATE-ORDER-01',
            'customer_name' => 'مشتری دیگر',
            'mobile' => '09125556666',
            'shipping_address' => 'نشانی محرمانه',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
        ]);

        $this->actingAs($customer)->post('/order/track', [
            'order_number' => $order->order_number,
            'mobile' => $order->mobile,
        ])->assertNotFound();
    }

    public function test_customer_cannot_retry_payment_for_another_mobile_order(): void
    {
        $customer = User::create([
            'name' => 'مشتری مجاز',
            'mobile' => '09123334445',
            'password' => Hash::make('customer-password'),
            'is_admin' => false,
        ]);
        $order = Order::create([
            'order_number' => 'CS-PRIVATE-PAY-01',
            'customer_name' => 'مشتری دیگر',
            'mobile' => '09125556667',
            'shipping_address' => 'نشانی محرمانه',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
        ]);

        $this->actingAs($customer)->post(route('orders.pay.retry', $order), [
            'order_number' => $order->order_number,
            'mobile' => $order->mobile,
        ])->assertNotFound();
    }

    public function test_customer_order_tracking_requires_authenticated_customer_session(): void
    {
        $this->post('/order/track', ['order_number' => 'CS-EXAMPLE', 'mobile' => '09121112222'])
            ->assertRedirect(route('customer.login'));
    }

    public function test_admin_account_cannot_use_customer_order_tracking(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/order/track', [
            'order_number' => 'CS-EXAMPLE',
            'mobile' => $admin->mobile,
        ])->assertRedirect(route('customer.login'));
    }
}
