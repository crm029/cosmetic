<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_returns_successful_response(): void
    {
        $this->get('/')->assertOk()->assertSee('زیبایی');
    }

    public function test_shop_page_returns_successful_response(): void
    {
        $this->get('/shop')->assertOk();
    }

    public function test_category_landing_page_is_available(): void
    {
        $category = Category::create(['name' => 'مراقبت پوست', 'slug' => 'skin-care', 'is_active' => true]);
        $this->get('/category/'.$category->slug)->assertOk()->assertSee('مراقبت پوست');
    }

    public function test_admin_is_protected_from_guests(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_customer_can_add_an_available_product_to_cart(): void
    {
        $product = Product::create(['name' => 'نمونه محصول', 'slug' => 'sample-product', 'price' => 120000, 'stock' => 5, 'is_active' => true]);
        $this->post('/cart/'.$product->id, ['quantity' => 2])->assertRedirect('/cart');
        $this->get('/cart')->assertOk()->assertSee('نمونه محصول')->assertSee('240,000');
    }

    public function test_checkout_uses_server_price_and_does_not_deduct_stock_before_payment(): void
    {
        $product = Product::create(['name' => 'محصول تست', 'slug' => 'checkout-product', 'price' => 120000, 'stock' => 5, 'is_active' => true]);
        $this->withSession(['cart' => [$product->id => 2]])->post('/checkout', [
            'customer_name' => 'مشتری آزمایشی',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه، پلاک ۱',
        ])->assertRedirect('/checkout');

        $this->assertDatabaseHas('orders', ['total' => 240000, 'payment_status' => 'unpaid']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_customer_can_track_order_with_order_number_and_mobile(): void
    {
        $order = Order::create([
            'order_number' => 'CS-261126-ABC123',
            'customer_name' => 'مشتری آزمایشی',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 150000,
            'shipping_amount' => 0,
            'total' => 150000,
        ]);

        $this->post('/order/track', [
            'order_number' => $order->order_number,
            'mobile' => '09123456789',
        ])->assertOk()->assertSee($order->order_number);
    }
    public function test_admin_cannot_cancel_a_paid_order_without_refund(): void
    {
        $admin = User::create(['name' => 'مدیر تست', 'mobile' => '09111111111', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-PAID01',
            'customer_name' => 'مشتری تست',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 150000,
            'shipping_amount' => 0,
            'total' => 150000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->id, ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);
    }

    public function test_unpaid_order_cannot_be_marked_as_shipped(): void
    {
        $admin = User::create(['name' => 'مدیر تست', 'mobile' => '09111111112', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-UNPAID1',
            'customer_name' => 'مشتری تست',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 150000,
            'shipping_amount' => 0,
            'total' => 150000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->id, ['status' => 'shipped'])
            ->assertSessionHasErrors('status');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
    }

}