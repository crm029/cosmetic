<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
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
        $this->assertTrue(DB::table('stock_reservations')->where('order_id', $order->id)->whereNotNull('consumed_at')->exists());
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

    public function test_admin_cannot_reduce_stock_below_active_reservations(): void
    {
        $admin = User::create(['name' => 'مدیر رزرو', 'mobile' => '09123456780', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'کالای رزروشده', 'slug' => 'admin-reserved-stock', 'price' => 100000, 'stock' => 5, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 4]], 'CS-ADMIN-RESERVE-01');
        app(StockReservationService::class)->reserveForOrder($order);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'stock' => 3,
            'is_active' => true,
        ])->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_admin_cannot_delete_product_with_active_reservation(): void
    {
        $admin = User::create(['name' => 'مدیر حذف رزرو', 'mobile' => '09123456781', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'کالای در انتظار پرداخت', 'slug' => 'admin-delete-reserved', 'price' => 100000, 'stock' => 2, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 1]], 'CS-ADMIN-RESERVE-02');
        app(StockReservationService::class)->reserveForOrder($order);

        $this->actingAs($admin)->delete(route('admin.products.delete', $product))
            ->assertSessionHasErrors('product');

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }


    public function test_admin_can_reconcile_paid_order_after_expired_reservation_when_stock_is_available(): void
    {
        $admin = User::create(['name' => 'مدیر تطبیق موجودی', 'mobile' => '09123456782', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'محصول قابل تامین', 'slug' => 'reconcile-expired-stock', 'price' => 100000, 'stock' => 3, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-RECONCILE-01');
        app(StockReservationService::class)->reserveForOrder($order);
        DB::table('stock_reservations')->where('order_id', $order->id)->update(['expires_at' => now()->subMinute()]);
        $order->update(['payment_status' => 'paid', 'status' => 'paid_stock_issue']);

        $this->actingAs($admin)
            ->post(route('admin.orders.reconcile', $order))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertTrue(DB::table('stock_reservations')->where('order_id', $order->id)->whereNotNull('released_at')->exists());
        $this->assertTrue(DB::table('stock_reservations')->where('order_id', $order->id)->whereNotNull('consumed_at')->exists());
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_cancelling_unpaid_order_releases_its_stock_reservation(): void
    {
        $admin = User::create(['name' => 'مدیر لغو سفارش', 'mobile' => '09123456783', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'محصول سفارش لغوشده', 'slug' => 'cancel-order-reservation', 'price' => 80000, 'stock' => 4, 'is_active' => true]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 3]], 'CS-CANCEL-RESERVE-01');
        app(StockReservationService::class)->reserveForOrder($order);

        $this->actingAs($admin)
            ->patch(route('admin.orders.update', $order), ['status' => 'cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertTrue(DB::table('stock_reservations')->where('order_id', $order->id)->whereNotNull('released_at')->exists());
        $this->assertFalse(DB::table('stock_reservations')->where('order_id', $order->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at', '>', now())->exists());
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
    }


    public function test_legacy_paid_order_cannot_take_stock_reserved_for_another_order(): void
    {
        $product = Product::create(['name' => 'محصول با رزرو فعال', 'slug' => 'legacy-cannot-steal-reserved', 'price' => 90000, 'stock' => 3, 'is_active' => true]);
        $reservedOrder = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-LEGACY-RESERVE-01');
        $legacyPaidOrder = $this->makeOrder([['product' => $product, 'quantity' => 2]], 'CS-LEGACY-RESERVE-02');
        app(StockReservationService::class)->reserveForOrder($reservedOrder);
        $legacyPaidOrder->update(['payment_status' => 'paid']);

        $this->assertFalse(app(OrderFulfillmentService::class)->deductStock($legacyPaidOrder));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['id' => $legacyPaidOrder->id, 'status' => 'paid_stock_issue']);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('stock_reservations', ['order_id' => $reservedOrder->id, 'quantity' => 2, 'released_at' => null]);
    }


    public function test_failed_product_update_keeps_old_media_and_cleans_new_uploads(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'مدیر فایل محصول', 'mobile' => '09123456784', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $oldImage = 'products/old-product-image.jpg';
        Storage::disk('public')->put($oldImage, 'old-image-content');
        $product = Product::create([
            'name' => 'محصول دارای تصویر', 'slug' => 'product-with-media-rollback',
            'price' => 100000, 'stock' => 5, 'image' => $oldImage, 'is_active' => true,
        ]);
        $order = $this->makeOrder([['product' => $product, 'quantity' => 4]], 'CS-MEDIA-ROLLBACK-01');
        app(StockReservationService::class)->reserveForOrder($order);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'stock' => 3,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('replacement.jpg'),
        ])->assertSessionHasErrors('stock');

        Storage::disk('public')->assertExists($oldImage);
        $this->assertCount(1, Storage::disk('public')->allFiles('products'));
        $this->assertDatabaseHas('products', ['id' => $product->id, 'image' => $oldImage, 'stock' => 5]);
    }

    public function test_successful_product_image_replacement_deletes_old_file_after_database_update(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'مدیر تعویض تصویر', 'mobile' => '09123456785', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $oldImage = 'products/old-product-image.jpg';
        Storage::disk('public')->put($oldImage, 'old-image-content');
        $product = Product::create([
            'name' => 'محصول قابل ویرایش', 'slug' => 'product-media-replacement',
            'price' => 100000, 'stock' => 5, 'image' => $oldImage, 'is_active' => true,
        ]);

        $this->actingAs($admin)->put(route('admin.products.update', $product), [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'stock' => 7,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('new-product.jpg'),
        ])->assertRedirect(route('admin.products'))->assertSessionHasNoErrors();

        Storage::disk('public')->assertMissing($oldImage);
        $updated = $product->fresh();
        $this->assertNotSame($oldImage, $updated->image);
        Storage::disk('public')->assertExists($updated->image);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 7]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 2,
            'previous_stock' => 5,
            'new_stock' => 7,
        ]);
    }


}
