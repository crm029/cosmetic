<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SalesReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر گزارش',
            'mobile' => '09123456790',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    private function makeOrder(string $number, string $mobile, string $payment, int $total, string $date): Order
    {
        $order = Order::create([
            'order_number' => $number,
            'customer_name' => 'مشتری گزارش',
            'mobile' => $mobile,
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => $total,
            'shipping_amount' => 0,
            'total' => $total,
            'status' => 'pending',
            'payment_status' => $payment,
        ]);
        $order->created_at = Carbon::parse($date);
        $order->save();

        return $order;
    }

    public function test_sales_report_calculates_paid_revenue_and_top_products_for_selected_dates(): void
    {
        $admin = $this->admin();
        $paid = $this->makeOrder('REP-PAID-01', '09111111111', 'paid', 100000, '2026-11-26 10:00:00');
        $unpaid = $this->makeOrder('REP-UNPAID-01', '09222222222', 'unpaid', 50000, '2026-11-26 11:00:00');
        $outside = $this->makeOrder('REP-OUTSIDE-01', '09333333333', 'paid', 200000, '2026-11-25 10:00:00');

        OrderItem::create(['order_id' => $paid->id, 'product_name' => 'سرم آبرسان', 'unit_price' => 50000, 'quantity' => 2, 'line_total' => 100000]);
        OrderItem::create(['order_id' => $outside->id, 'product_name' => 'کرم مرطوب‌کننده', 'unit_price' => 200000, 'quantity' => 1, 'line_total' => 200000]);

        $this->actingAsAdmin($admin)->get(route('admin.reports', ['from' => '2026-11-26', 'to' => '2026-11-26']))
            ->assertOk()
            ->assertSee('100,000')
            ->assertSee('سرم آبرسان')
            ->assertDontSee('کرم مرطوب‌کننده')
            ->assertSee('مشتری گزارش');
    }

    public function test_sales_report_rejects_ranges_longer_than_one_year(): void
    {
        $admin = $this->admin();

        $this->actingAsAdmin($admin)->get(route('admin.reports', [
            'from' => '2025-01-01',
            'to' => '2026-11-27',
        ]))->assertSessionHasErrors('to');
    }
}
