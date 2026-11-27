<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderFulfillmentService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class StockReservationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(array $items, string $number): Order
    {
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['product']->price * $item['quantity'];
        }

        $order = Order::create([
            'order_number' => $number,
            'customer_name' => 'مشتری تست رزرو',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => $subtotal,
            'shipping_amount' => 0,
            'total' => $subtotal,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        foreach ($items as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item['product']->id,
                'product_name' => $item['product']->name,
                'unit_price' => $item['product']->price,
                'quantity' => $item['quantity'],
                'line_total' => $item['product']->price * $item['quantity'],
            ]);
        }

        return $order;
    }

    public function test_reservation_blocks_other_orders_without_reducing_physical_stock(): void
    {
        $product = Product::create(['name' => 'کرم رزروی', 'slug' => 'reserved-cream', 'price' => 100000, 'stock' => 2, 'is_active' => true]);
        $first = $this->makeOrder([['product' => $product, 'quantity' => 1]], 'CS-RESERVE-01');
        $second = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RESERVE-02');
        $service = app(StockReservationService::class);

        $service->reserveForOrder($first);
        try {
            $service->reserveForOrder($second);
            $this->fail('رزرو بیشتر از موجودی آزاد باید رد شود.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('stock_reservations', 1);
        }

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $first->id, 'quantity' => 1, 'released_at' => null]);
    }

    public function test_expired_reservation_does_not_block_a_new_order(): void
    {
        $product = Product::create(['name' => 'سرم رزروی', 'slug' => 'expired-reserved-serum', 'price' => 90000, 'stock' => 2, 'is_active' => true]);
        $first = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RESERVE-03');
        $second = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RESERVE-04');
        $service = app(StockReservationService::class);

        $service->reserveForOrder($first);
        DB::table('stock_reservations')->where('order_id', $first->id)->update(['expires_at' => now()->subMinute()]);
        $service->reserveForOrder($second);

        $this->assertDatabaseHas('stock_reservations', ['order_id' => $second->id, 'quantity' => 2, 'released_at' => null]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
    }

    public function test_successful_fulfillment_consumes_reservation_and_deducts_stock_once(): void
    {
        $product = Product::create(['name' => 'لوسیون رزروی', 'slug' => 'consume-reserved-lotion', 'price' => 80000, 'stock' => 3, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RESERVE-05');
        app(StockReservationService::class)->reserveForOrder($order);
        $order->update(['payment_status' => 'paid']);

        $fulfillment = app(OrderFulfillmentService::class);
        $this->assertTrue($fulfillment->deductStock($order));
        $this->assertTrue($fulfillment->deductStock($order->fresh()));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $order->id, 'consumed_at' => now()]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_paid_order_with_expired_reservation_is_flagged_without_deducting_stock(): void
    {
        $product = Product::create(['name' => 'ماسک رزروی', 'slug' => 'expired-paid-mask', 'price' => 70000, 'stock' => 3, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RESERVE-06');
        app(StockReservationService::class)->reserveForOrder($order);
        DB::table('stock_reservations')->where('order_id', $order->id)->update(['expires_at' => now()->subMinute()]);
        $order->update(['payment_status' => 'paid']);

        $this->assertFalse(app(OrderFulfillmentService::class)->deductStock($order));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid_stock_issue', 'stock_deducted_at' => null]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_multi_product_reservation_is_all_or_nothing(): void
    {
        $available = Product::create(['name' => 'محصول کافی', 'slug' => 'all-or-nothing-ok', 'price' => 50000, 'stock' => 2, 'is_active' => true]);
        $shortage = Product::create(['name' => 'محصول ناکافی', 'slug' => 'all-or-nothing-short', 'price' => 60000, 'stock' => 1, 'is_active' => true]);
        $order = $this->makeOrder([
            ['product' => $available, 'quantity' => 1],
            ['product' => $shortage, 'quantity' => 2],
        ], 'CS-RESERVE-07');

        try {
            app(StockReservationService::class)->reserveForOrder($order);
            $this->fail('رزرو چندمحصولی با کمبود یک قلم باید کامل رد شود.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('stock_reservations', 0);
        }

        $this->assertDatabaseHas('products', ['id' => $available->id, 'stock' => 2]);
        $this->assertDatabaseHas('products', ['id' => $shortage->id, 'stock' => 1]);
    }
}
