<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\StockReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentOrderLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function paidCandidate(string $number): array
    {
        $product = Product::create([
            'name' => 'محصول پرداخت',
            'slug' => 'payment-product-'.strtolower($number),
            'price' => 120000,
            'stock' => 4,
            'is_active' => true,
        ]);

        $order = Order::create([
            'order_number' => $number,
            'customer_name' => 'مشتری پرداخت',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست پرداخت',
            'subtotal' => 240000,
            'shipping_amount' => 0,
            'total' => 240000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_authority' => 'S-TEST-AUTHORITY-'.strtolower($number),
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => $product->price,
            'quantity' => 2,
            'line_total' => 240000,
        ]);

        app(StockReservationService::class)->reserveForOrder($order);

        return [$order, $product];
    }

    public function test_successful_gateway_callback_confirms_payment_and_deducts_stock_only_once(): void
    {
        config([
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
            'services.zarinpal.merchant_id' => '00000000-0000-0000-0000-000000000000',
        ]);

        [$order, $product] = $this->paidCandidate('CS-PAYMENT-CALLBACK-01');

        Http::fake([
            'sandbox.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 987654321],
                'errors' => [],
            ], 200),
        ]);

        $url = route('payment.callback', [
            'Status' => 'OK',
            'Authority' => $order->payment_authority,
        ]);

        $this->get($url)->assertRedirect(route('home'));
        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'paid',
            'payment_ref_id' => '987654321',
            'status' => 'processing',
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'pending',
            'to_status' => 'processing',
            'actor_type' => 'payment_gateway',
        ]);

        $this->get($url)->assertRedirect(route('home'));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_customer_cancelled_payment_releases_reservation_without_marking_order_paid(): void
    {
        [$order, $product] = $this->paidCandidate('CS-PAYMENT-CALLBACK-02');

        $this->get(route('payment.callback', [
            'Status' => 'NOK',
            'Authority' => $order->payment_authority,
        ]))->assertRedirect(route('home'));

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'unpaid',
            'payment_authority' => null,
        ]);
        $this->assertSame(0, DB::table('stock_reservations')
            ->where('order_id', $order->id)
            ->whereNull('released_at')
            ->whereNull('consumed_at')
            ->count());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
        $this->assertDatabaseCount('stock_movements', 0);
    }
}
