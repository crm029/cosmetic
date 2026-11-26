<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
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
        ])->assertRedirect('/checkout')->assertSessionHasErrors('checkout');

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

    public function test_admin_can_create_a_product_with_gallery_images(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'مدیر گالری', 'mobile' => '09111111113', 'password' => 'a-secure-test-password', 'is_admin' => true]);

        $this->actingAs($admin)->post('/admin/products', [
            'name' => 'سرم تست',
            'slug' => 'test-serum',
            'price' => 250000,
            'stock' => 7,
            'gallery_images' => [
                UploadedFile::fake()->image('serum-front.jpg'),
                UploadedFile::fake()->image('serum-side.jpg'),
            ],
        ])->assertRedirect('/admin/products');

        $product = Product::where('slug', 'test-serum')->firstOrFail();
        $this->assertCount(2, $product->gallery);
        foreach ($product->gallery as $image) {
            Storage::disk('public')->assertExists($image);
        }
    }

    public function test_admin_can_upload_a_category_cover_image(): void
    {
        Storage::fake('public');
        $admin = User::create(['name' => 'مدیر دسته', 'mobile' => '09111111114', 'password' => 'a-secure-test-password', 'is_admin' => true]);

        $this->actingAs($admin)->post('/admin/categories', [
            'name' => 'مراقبت بدن',
            'slug' => 'body-care-test',
            'image' => UploadedFile::fake()->image('body-care.jpg'),
            'is_active' => '1',
        ])->assertRedirect('/admin/categories');

        $category = Category::where('slug', 'body-care-test')->firstOrFail();
        $this->assertNotEmpty($category->image);
        Storage::disk('public')->assertExists($category->image);
    }

    public function test_customer_contact_message_is_saved(): void
    {
        $this->post('/contact', [
            'name' => 'مشتری تست',
            'mobile' => '09123456789',
            'email' => 'customer@example.test',
            'subject' => 'پرسش درباره محصول',
            'message' => 'لطفاً درباره روش استفاده از این محصول راهنمایی کنید.',
        ])->assertRedirect('/contact');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'مشتری تست',
            'mobile' => '09123456789',
            'subject' => 'پرسش درباره محصول',
            'is_read' => false,
        ]);
    }

    public function test_security_headers_are_present_on_public_pages(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_admin_can_read_and_mark_customer_messages(): void
    {
        $admin = User::create(['name' => 'مدیر پشتیبانی', 'mobile' => '09111111115', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $message = ContactMessage::create([
            'name' => 'مشتری پیام‌دهنده',
            'mobile' => '09123456789',
            'subject' => 'سؤال تست',
            'message' => 'این یک پیام آزمایشی برای صندوق پشتیبانی است.',
        ]);

        $this->actingAs($admin)->get('/admin/messages')->assertOk()->assertSee('این یک پیام آزمایشی');
        $this->patch('/admin/messages/'.$message->id.'/read')->assertRedirect();
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'is_read' => true]);
    }

    public function test_about_and_faq_pages_are_available(): void
    {
        $this->get('/about')->assertOk()->assertSee('داستان ما');
        $this->get('/faq')->assertOk()->assertSee('چطور سفارش ثبت کنم؟');
        $this->get('/privacy')->assertOk()->assertSee('حریم خصوصی');
        $this->get('/terms')->assertOk()->assertSee('شرایط خرید');
    }

    public function test_product_detail_page_renders_product_and_gallery_schema(): void
    {
        $product = Product::create([
            'name' => 'سرم آزمایشی',
            'slug' => 'schema-serum',
            'price' => 350000,
            'stock' => 3,
            'is_active' => true,
            'gallery' => ['products/gallery/serum-side.webp'],
        ]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('سرم آزمایشی')
            ->assertSee('BreadcrumbList', false)
            ->assertSee('"@type":"Product"', false);
    }

    public function test_sitemap_contains_public_information_pages(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee('/about', false)
            ->assertSee('/faq', false)
            ->assertSee('/contact', false)
            ->assertSee('/privacy', false)
            ->assertSee('/terms', false);
    }

    public function test_successful_payment_deducts_stock_once_and_sends_confirmation_sms(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment',
            'services.0098sms.base_url' => 'https://sms.test/send',
            'services.0098sms.username' => 'test-user',
            'services.0098sms.password' => 'test-password',
            'services.0098sms.sender' => '10001000',
        ]);
        Http::fake([
            'api.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 987654]], 200),
            'sms.test/*' => Http::response('1', 200),
        ]);

        $product = Product::create(['name' => 'محصول پرداخت', 'slug' => 'paid-product', 'price' => 100000, 'stock' => 5, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-PAY001',
            'customer_name' => 'مشتری پرداخت',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_authority' => 'A000000000000000000000000000001',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100000, 'quantity' => 2, 'line_total' => 200000]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'processing']);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertNotNull($order->fresh()->sms_sent_at);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        Http::assertSentCount(2);
    }

    public function test_catalog_filters_products_by_price_and_stock(): void
    {
        Product::create(['name' => 'Cheap Filter Product', 'slug' => 'cheap-item', 'price' => 90000, 'stock' => 5, 'is_active' => true]);
        Product::create(['name' => 'Inside Filter Product', 'slug' => 'middle-item', 'price' => 180000, 'stock' => 4, 'is_active' => true]);
        Product::create(['name' => 'Out Of Stock Filter Product', 'slug' => 'out-item', 'price' => 200000, 'stock' => 0, 'is_active' => true]);
        Product::create(['name' => 'Expensive Filter Product', 'slug' => 'expensive-item', 'price' => 500000, 'stock' => 2, 'is_active' => true]);

        $this->get('/shop?min_price=100000&max_price=250000&in_stock=1&sort=price_asc')
            ->assertOk()
            ->assertSee('Inside Filter Product')
            ->assertDontSee('Cheap Filter Product')
            ->assertDontSee('Out Of Stock Filter Product')
            ->assertDontSee('Expensive Filter Product');
    }

    public function test_admin_can_update_store_settings_and_shipping_policy(): void
    {
        $admin = User::create(['name' => 'مدیر تنظیمات', 'mobile' => '09111111116', 'password' => 'a-secure-test-password', 'is_admin' => true]);

        $this->actingAs($admin)->get('/admin/settings')->assertOk()->assertSee('تنظیمات فروشگاه');

        $this->actingAs($admin)->put('/admin/settings', [
            'store_name' => 'فروشگاه زیبایی تست',
            'tagline' => 'مراقبت با انتخاب آگاهانه',
            'shipping_fee_toman' => 12000,
            'free_shipping_threshold_toman' => 300000,
            'support_mobile' => '09123456789',
            'support_email' => 'support@example.test',
            'address' => 'تهران، نشانی نمونه',
            'ink_color' => '#29251f',
            'accent_color' => '#a77d6a',
        ])->assertRedirect();

        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'store_name' => 'فروشگاه زیبایی تست',
            'shipping_fee_toman' => 12000,
            'free_shipping_threshold_toman' => 300000,
        ]);

        $this->get('/')->assertOk()
            ->assertSee('فروشگاه زیبایی تست')
            ->assertSee('09123456789')
            ->assertSee('support@example.test')
            ->assertSee('--ink: #29251f', false);
    }

    public function test_checkout_uses_admin_configured_shipping_fee(): void
    {
        StoreSetting::current()->update(['shipping_fee_toman' => 12000, 'free_shipping_threshold_toman' => 300000]);
        $product = Product::create(['name' => 'محصول با ارسال', 'slug' => 'shipping-product', 'price' => 100000, 'stock' => 4, 'is_active' => true]);

        $this->withSession(['cart' => [$product->id => 1]])->get('/checkout')
            ->assertOk()
            ->assertSee('12,000')
            ->assertSee('112,000');
    }

    public function test_filtered_catalog_is_noindex_but_base_catalog_is_indexable(): void
    {
        $this->get('/shop')->assertOk()->assertSee('content="index,follow"', false);
        $this->get('/shop?q=serum')->assertOk()->assertSee('content="noindex,nofollow"', false);
    }

    public function test_admin_can_retry_fulfillment_for_paid_stock_issue_order(): void
    {
        $admin = User::create(['name' => 'مدیر انبار', 'mobile' => '09111111117', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'محصول کم‌موجودی', 'slug' => 'low-stock-item', 'price' => 100000, 'stock' => 1, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-STOCK01',
            'customer_name' => 'مشتری پرداخت‌شده',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'paid_stock_issue',
            'payment_status' => 'paid',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100000, 'quantity' => 2, 'line_total' => 200000]);

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/reconcile')->assertSessionHasErrors('stock');
        $this->patch('/admin/orders/'.$order->id, ['status' => 'shipped'])->assertSessionHasErrors('status');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);

        $product->update(['stock' => 3]);
        $this->post('/admin/orders/'.$order->id.'/reconcile')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'processing']);

        $this->post('/admin/orders/'.$order->id.'/reconcile')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
    }

    public function test_late_payment_callback_cannot_settle_a_cancelled_order(): void
    {
        Http::fake();
        $order = Order::create([
            'order_number' => 'CS-261126-CANCEL1',
            'customer_name' => 'مشتری لغوکننده',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'cancelled',
            'payment_status' => 'unpaid',
            'payment_authority' => 'A000000000000000000000000000002',
        ]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')
            ->assertRedirect('/')
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled', 'payment_status' => 'unpaid']);
        Http::assertNothingSent();
    }

    public function test_admin_can_filter_products_by_search_and_visibility(): void
    {
        $admin = User::create(['name' => 'مدیر کاتالوگ', 'mobile' => '09111111118', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        Product::create(['name' => 'سرم فعال', 'slug' => 'active-serum', 'sku' => 'SERUM-101', 'price' => 100000, 'stock' => 4, 'is_active' => true]);
        Product::create(['name' => 'سرم پیش‌نویس', 'slug' => 'draft-serum', 'sku' => 'SERUM-102', 'price' => 120000, 'stock' => 2, 'is_active' => false]);

        $this->actingAs($admin)->get('/admin/products?q=SERUM&visibility=active')
            ->assertOk()
            ->assertSee('سرم فعال')
            ->assertDontSee('سرم پیش‌نویس');
    }

    public function test_admin_can_filter_orders_by_status_and_payment(): void
    {
        $admin = User::create(['name' => 'مدیر سفارش', 'mobile' => '09111111119', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $paid = Order::create(['order_number' => 'CS-PAID-FILTER', 'customer_name' => 'مشتری اول', 'mobile' => '09123456789', 'shipping_address' => 'تهران، خیابان نمونه', 'subtotal' => 100000, 'shipping_amount' => 0, 'total' => 100000, 'status' => 'processing', 'payment_status' => 'paid']);
        Order::create(['order_number' => 'CS-UNPAID-FILTER', 'customer_name' => 'مشتری دوم', 'mobile' => '09123456780', 'shipping_address' => 'تهران، خیابان نمونه', 'subtotal' => 100000, 'shipping_amount' => 0, 'total' => 100000, 'status' => 'pending', 'payment_status' => 'unpaid']);

        $this->actingAs($admin)->get('/admin/orders?q=CS-PAID&status=processing&payment_status=paid')
            ->assertOk()
            ->assertSee($paid->order_number)
            ->assertDontSee('CS-UNPAID-FILTER');
    }

    public function test_admin_can_add_shipment_tracking_and_customer_can_view_it(): void
    {
        $admin = User::create(['name' => 'مدیر ارسال', 'mobile' => '09111111120', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $order = Order::create([
            'order_number' => 'CS-SHIP-TRACK01',
            'customer_name' => 'مشتری ارسال',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'processing',
            'payment_status' => 'paid',
            'stock_deducted_at' => now(),
        ]);

        $this->actingAs($admin)->patch('/admin/orders/'.$order->id, [
            'status' => 'shipped',
            'shipping_carrier' => 'پست پیشتاز',
            'tracking_code' => '123456789012345',
        ])->assertRedirect();

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'shipped', 'tracking_code' => '123456789012345']);
        $this->post('/order/track', ['order_number' => $order->order_number, 'mobile' => '09123456789'])
            ->assertOk()
            ->assertSee('123456789012345')
            ->assertSee('پست پیشتاز');
    }

    public function test_customer_can_retry_payment_for_an_unpaid_order(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment',
            'services.zarinpal.startpay_url' => 'https://www.zarinpal.com/pg/StartPay',
        ]);
        Http::fake([
            'api.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'authority' => 'AUTH-RETRY-123']], 200),
        ]);
        $order = Order::create([
            'order_number' => 'CS-RETRY-ORDER',
            'customer_name' => 'مشتری پرداخت مجدد',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 175000,
            'shipping_amount' => 0,
            'total' => 175000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->post('/order/'.$order->id.'/pay', [
            'order_number' => $order->order_number,
            'mobile' => $order->mobile,
        ])->assertRedirect('https://www.zarinpal.com/pg/StartPay/AUTH-RETRY-123');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_authority' => 'AUTH-RETRY-123']);
    }

    public function test_admin_cannot_set_compare_price_below_selling_price(): void
    {
        $admin = User::create(['name' => 'مدیر قیمت', 'mobile' => '09111111121', 'password' => 'a-secure-test-password', 'is_admin' => true]);

        $this->actingAs($admin)->post('/admin/products', [
            'name' => 'محصول قیمت‌گذاری',
            'slug' => 'price-validation-product',
            'price' => 150000,
            'compare_price' => 120000,
            'stock' => 2,
        ])->assertSessionHasErrors('compare_price');

        $this->assertDatabaseMissing('products', ['slug' => 'price-validation-product']);
    }

}