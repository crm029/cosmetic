<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'مدیر تاریخچه',
            'mobile' => '09123456786',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
    }

    private function order(string $number, string $status = 'processing', string $payment = 'paid'): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => 'مشتری تاریخچه',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 150000,
            'shipping_amount' => 0,
            'total' => 150000,
            'status' => $status,
            'payment_status' => $payment,
            'stock_deducted_at' => $status === 'processing' ? now() : null,
        ]);
    }

    public function test_admin_shipping_transition_is_saved_with_actor_and_previous_status(): void
    {
        $admin = $this->admin();
        $order = $this->order('CS-HISTORY-ADMIN-01');

        $this->actingAs($admin)->patch(route('admin.orders.update', $order), [
            'status' => 'shipped',
            'shipping_carrier' => 'پست',
            'tracking_code' => 'HISTORY-TRACK-01',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'admin_user_id' => $admin->id,
            'from_status' => 'processing',
            'to_status' => 'shipped',
            'actor_type' => 'admin',
        ]);
    }

    public function test_manual_refund_recording_is_audited(): void
    {
        $admin = $this->admin();
        $order = $this->order('CS-HISTORY-REFUND-01', 'refund_required', 'paid');
        $order->update(['stock_deducted_at' => null]);

        $this->actingAs($admin)->post(route('admin.orders.refund', $order), [
            'refund_ref_id' => 'REFUND-TRACK-01',
            'refund_note' => 'بازپرداخت در پنل درگاه انجام شد.',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'admin_user_id' => $admin->id,
            'from_status' => 'refund_required',
            'to_status' => 'cancelled',
            'actor_type' => 'admin',
        ]);
    }

    public function test_system_stock_issue_is_recorded_in_order_history(): void
    {
        $order = $this->order('CS-HISTORY-STOCK-01', 'pending', 'paid');
        $order->update(['stock_deducted_at' => null]);

        $this->assertFalse(app(\App\Services\OrderFulfillmentService::class)->deductStock($order));

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'paid_stock_issue',
            'actor_type' => 'system',
        ]);
    }

    public function test_admin_order_details_display_the_status_timeline(): void
    {
        $admin = $this->admin();
        $order = $this->order('CS-HISTORY-VIEW-01');
        \App\Models\OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => 'processing',
            'to_status' => 'shipped',
            'actor_type' => 'system',
            'note' => 'مرسوله تحویل شرکت پست شد.',
        ]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()
            ->assertSee('تاریخچه تغییرات سفارش')
            ->assertSee('ارسال‌شده')
            ->assertSee('مرسوله تحویل شرکت پست شد.');
    }
}
