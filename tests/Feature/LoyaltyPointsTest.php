<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderFulfillmentService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyPointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_points_are_awarded_once_after_paid_order_stock_fulfillment(): void
    {
        $user = User::create([
            'name' => 'مشتری وفادار',
            'mobile' => '09123456780',
            'password' => 'a-secure-test-password',
            'is_admin' => false,
        ]);
        $product = Product::create([
            'name' => 'محصول امتیازی',
            'slug' => 'loyalty-award-product',
            'price' => 250000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'CS-LOYALTY-001',
            'customer_name' => $user->name,
            'mobile' => $user->mobile,
            'shipping_address' => 'تهران، نشانی آزمایشی معتبر',
            'subtotal' => 250000,
            'discount_amount' => 50000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 250000,
            'quantity' => 1,
            'line_total' => 250000,
        ]);

        app(StockReservationService::class)->reserveForOrder($order);
        $fulfillment = app(OrderFulfillmentService::class);

        $this->assertTrue($fulfillment->deductStock($order));
        $this->assertTrue($fulfillment->deductStock($order->fresh()));

        $this->assertSame(20, (int) $user->fresh()->loyalty_points);
        $this->assertDatabaseCount('loyalty_point_transactions', 1);
        $this->assertDatabaseHas('loyalty_point_transactions', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'type' => 'earned',
            'points' => 20,
        ]);
    }

    public function test_guest_orders_do_not_create_unowned_loyalty_points(): void
    {
        $product = Product::create([
            'name' => 'محصول مهمان',
            'slug' => 'guest-loyalty-product',
            'price' => 30000,
            'stock' => 2,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CS-LOYALTY-GUEST',
            'customer_name' => 'مهمان',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی آزمایشی معتبر',
            'subtotal' => 30000,
            'shipping_amount' => 0,
            'total' => 30000,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 30000,
            'quantity' => 1,
            'line_total' => 30000,
        ]);

        app(StockReservationService::class)->reserveForOrder($order);
        $this->assertTrue(app(OrderFulfillmentService::class)->deductStock($order));
        $this->assertDatabaseCount('loyalty_point_transactions', 0);
    }
}
