<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر مشتریان',
            'mobile' => '09123456788',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    private function order(string $number, string $name, string $mobile, string $payment, int $total): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => $name,
            'mobile' => $mobile,
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => $total,
            'shipping_amount' => 0,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => $payment,
        ]);
    }

    public function test_customer_directory_groups_orders_by_mobile_and_counts_paid_spend_only(): void
    {
        $admin = $this->admin();
        $this->order('CUS-001', 'مشتری الف', '09111111111', 'paid', 100000);
        $this->order('CUS-002', 'مشتری الف', '09111111111', 'unpaid', 50000);
        $this->order('CUS-003', 'مشتری ب', '09222222222', 'paid', 70000);

        $this->actingAsAdmin($admin)->get(route('admin.customers', ['q' => '09111111111']))
            ->assertOk()
            ->assertSee('مشتری الف')
            ->assertSee('09111111111')
            ->assertSee('100,000')
            ->assertDontSee('مشتری ب');
    }

    public function test_customer_profile_shows_only_the_selected_mobile_order_history(): void
    {
        $admin = $this->admin();
        $this->order('CUS-DETAIL-01', 'مشتری جزئیات', '09333333333', 'paid', 250000);
        $this->order('CUS-DETAIL-02', 'مشتری جزئیات', '09333333333', 'unpaid', 80000);
        $this->order('CUS-OTHER-01', 'مشتری دیگر', '09444444444', 'paid', 99000);

        $this->actingAsAdmin($admin)->get(route('admin.customers.show', '09333333333'))
            ->assertOk()
            ->assertSee('CUS-DETAIL-01')
            ->assertSee('CUS-DETAIL-02')
            ->assertSee('250,000')
            ->assertDontSee('CUS-OTHER-01');
    }

    public function test_customer_directory_is_protected_from_non_admin_users(): void
    {
        $user = User::create([
            'name' => 'کاربر عادی',
            'mobile' => '09123456789',
            'password' => 'a-secure-test-password',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get(route('admin.customers'))->assertRedirect(route('admin.login'));
    }
}
