<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\OrderFulfillmentService;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderFulfillmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function order(string $number): Order
    {
        return Order::create([
            'order_number' => $number,
            'customer_name' => 'مشتری تست',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
    }

    private function product(string $slug): Product
    {
        return Product::create([
            'name' => 'محصول تست',
            'slug' => $slug,
            'price' => 100000,
            'stock' => 5,
            'is_active' => true,
        ]);
    }

    private function addItem(Order $order, Product $product, int $quantity = 1): void
    {
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => $quantity,
            'line_total' => $product->price * $quantity,
        ]);
    }

    public function test_paid_order_without_items_is_flagged_instead_of_marked_fulfilled(): void
    {
        $order = $this->order('CS-EMPTY-ORDER');

        $this->assertFalse(app(OrderFulfillmentService::class)->deductStock($order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_stock_issue',
            'stock_deducted_at' => null,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_extra_active_reservation_is_detected_and_released(): void
    {
        $orderedProduct = $this->product('integrity-ordered-product');
        $unexpectedProduct = $this->product('integrity-unexpected-product');
        $order = $this->order('CS-EXTRA-RESERVATION');
        $this->addItem($order, $orderedProduct, 1);

        app(StockReservationService::class)->reserveForOrder($order);

        DB::table('stock_reservations')->insert([
            'order_id' => $order->id,
            'product_id' => $unexpectedProduct->id,
            'quantity' => 1,
            'expires_at' => now()->addMinutes(20),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(OrderFulfillmentService::class)->deductStock($order));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_stock_issue',
            'stock_deducted_at' => null,
        ]);
        $this->assertDatabaseHas('products', ['id' => $orderedProduct->id, 'stock' => 5]);
        $this->assertDatabaseHas('products', ['id' => $unexpectedProduct->id, 'stock' => 5]);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertSame(0, DB::table('stock_reservations')
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->count());
    }

    public function test_fulfillment_is_idempotent_even_when_called_again_after_stock_deduction(): void
    {
        $product = $this->product('integrity-idempotent-product');
        $order = $this->order('CS-IDEMPOTENT-FULFILLMENT');
        $this->addItem($order, $product, 2);
        app(StockReservationService::class)->reserveForOrder($order);

        $fulfillment = app(OrderFulfillmentService::class);

        $this->assertTrue($fulfillment->deductStock($order));
        $this->assertTrue($fulfillment->deductStock($order->fresh()));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseCount('stock_movements', 1);
    }
}
