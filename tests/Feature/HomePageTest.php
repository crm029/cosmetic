<?php
namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_paid_sms_is_claimed_and_sent_only_once(): void
    {
        config([
            'services.0098sms.base_url' => 'https://sms.example.test/send',
            'services.0098sms.username' => 'test-user',
            'services.0098sms.password' => 'test-password',
            'services.0098sms.sender' => '1000',
            'services.0098sms.domain' => '0098',
        ]);
        Http::fake(['sms.example.test/*' => Http::response('0', 200)]);
        $order = Order::create([
            'order_number' => 'CS-SMS-ONCE-01',
            'customer_name' => 'مشتری پیامک',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $sms = app(SmsService::class);
        $this->assertTrue($sms->sendOrderPaidOnce($order));
        $this->assertFalse($sms->sendOrderPaidOnce($order->fresh()));

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($order) {
            return parse_url($request->url(), PHP_URL_PATH) === '/send'
                && $request['FROM'] === '1000'
                && $request['TO'] === $order->mobile
                && $request['TEXT'] !== ''
                && $request['USERNAME'] === 'test-user'
                && $request['PASSWORD'] === 'test-password'
                && $request['DOMAIN'] === '0098';
        });
        $this->assertNotNull($order->fresh()->sms_sent_at);
        $this->assertNull($order->fresh()->sms_claimed_at);
    }


    public function test_0098sms_error_code_is_not_treated_as_success(): void
    {
        config([
            'services.0098sms.base_url' => 'https://sms.example.test/send',
            'services.0098sms.username' => 'test-user',
            'services.0098sms.password' => 'test-password',
            'services.0098sms.sender' => '1000',
            'services.0098sms.domain' => '0098',
        ]);
        Http::fake(['sms.example.test/*' => Http::response('9', 200)]);
        $order = Order::create([
            'order_number' => 'CS-SMS-ERROR-01',
            'customer_name' => 'مشتری خطای پیامک',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $this->assertFalse(app(SmsService::class)->sendOrderPaidOnce($order));
        $this->assertNull($order->fresh()->sms_sent_at);
        $this->assertNull($order->fresh()->sms_claimed_at);
        Http::assertSentCount(1);
    }

    public function test_security_headers_are_present_on_store_pages(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeader('X-DNS-Prefetch-Control', 'off')
            ->assertHeader('Origin-Agent-Cluster', '?1');
    }

    public function test_sitemap_includes_active_products_and_last_modified_dates(): void
    {
        $category = Category::create(['name' => 'مراقبت پوست', 'slug' => 'sitemap-skin', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'محصول نقشه سایت',
            'slug' => 'sitemap-product',
            'price' => 150000,
            'stock' => 3,
            'is_active' => true,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('products.show', $product), false)
            ->assertSee('<lastmod>', false);
    }

    public function test_contact_honeypot_rejects_bot_submission(): void
    {
        $this->post('/contact', [
            'name' => 'ربات',
            'message' => 'این پیام آزمایشی ربات است.',
            'website' => 'https://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertDatabaseCount('contact_messages', 0);
    }

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
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'initial',
            'quantity' => 7,
            'previous_stock' => 0,
            'new_stock' => 7,
        ]);
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

        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
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
        $category = Category::create(['name' => 'مراقبت پوست', 'slug' => 'serum-category', 'is_active' => true]);
        $product = Product::create([
            'name' => 'سرم آزمایشی',
            'category_id' => $category->id,
            'slug' => 'schema-serum',
            'price' => 350000,
            'stock' => 3,
            'is_active' => true,
            'gallery' => ['products/gallery/serum-side.webp'],
        ]);

        Product::create(['name' => 'کرم مکمل', 'slug' => 'related-cream', 'category_id' => $category->id, 'price' => 200000, 'stock' => 2, 'is_active' => true]);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('سرم آزمایشی')
            ->assertSee('کرم مکمل')
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
            'sms.test/*' => Http::response('0', 200),
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
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'sale',
            'quantity' => -2,
            'previous_stock' => 5,
            'new_stock' => 3,
        ]);

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
        Storage::fake('public');
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
            'logo' => UploadedFile::fake()->image('store-logo.png'),
        ])->assertRedirect();

        $this->assertDatabaseHas('store_settings', [
            'id' => 1,
            'store_name' => 'فروشگاه زیبایی تست',
            'shipping_fee_toman' => 12000,
            'free_shipping_threshold_toman' => 300000,
        ]);
        $this->assertNotEmpty(StoreSetting::current()->logo_path);
        Storage::disk('public')->assertExists(StoreSetting::current()->logo_path);

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

    public function test_late_payment_callback_records_successful_payment_on_cancelled_order_for_refund(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'e3f4743b-58e6-4e14-b61f-dd5b71e59355',
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 987659]], 200),
        ]);
        $product = Product::create(['name' => 'محصول سفارش لغوشده', 'slug' => 'cancelled-order-late-payment', 'price' => 100000, 'stock' => 2, 'is_active' => true]);
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
            'payment_authority' => 'S0000000000000000000000000000004',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100000, 'quantity' => 1, 'line_total' => 100000]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')
            ->assertRedirect('/')
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'refund_required',
            'payment_status' => 'paid',
            'payment_ref_id' => '987659',
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertDatabaseMissing('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
        Http::assertSent(fn ($request) => $request->url() === 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json');
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
        config(['services.0098sms.base_url'=>'https://sms.test/send','services.0098sms.username'=>'test-user','services.0098sms.password'=>'test-password','services.0098sms.sender'=>'10001000']);
        Http::fake(['sms.test/*'=>Http::response('0',200)]);
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
        $this->assertNotNull($order->fresh()->shipment_sms_sent_at);
        Http::assertSentCount(1);
        $this->patch('/admin/orders/'.$order->id, ['status' => 'shipped'])->assertRedirect();
        Http::assertSentCount(1);
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

    public function test_checkout_removes_unavailable_products_from_stale_cart(): void
    {
        $product = Product::create(['name' => 'محصول غیرفعال', 'slug' => 'inactive-cart-item', 'price' => 100000, 'stock' => 3, 'is_active' => false]);

        $this->withSession(['cart' => [$product->id => 1]])->get('/checkout')
            ->assertRedirect('/cart')
            ->assertSessionHasErrors('cart')
            ->assertSessionHas('cart', []);
    }

    public function test_admin_stock_adjustments_are_recorded_in_inventory_history(): void
    {
        $admin = User::create(['name' => 'مدیر موجودی', 'mobile' => '09111111122', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $product = Product::create(['name' => 'محصول انبار', 'slug' => 'inventory-item', 'price' => 100000, 'stock' => 5, 'is_active' => true]);

        $this->actingAs($admin)->put('/admin/products/'.$product->id, [
            'name' => $product->name,
            'slug' => $product->slug,
            'price' => $product->price,
            'stock' => 9,
            'is_active' => '1',
        ])->assertRedirect('/admin/products');

        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => 4,
            'previous_stock' => 5,
            'new_stock' => 9,
            'admin_user_id' => $admin->id,
        ]);

        $this->get('/admin/products/'.$product->id.'/stock')
            ->assertOk()
            ->assertSee('اصلاح دستی')
            ->assertSee('+4');
    }

    public function test_paid_order_with_one_insufficient_item_does_not_deduct_any_other_item(): void
    {
        $admin = User::create(['name' => 'مدیر تست اتمیک', 'mobile' => '09111111201', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $this->actingAs($admin);
        $available = Product::create(['name' => 'محصول موجود', 'slug' => 'available-item-atomic', 'price' => 50000, 'stock' => 5, 'is_active' => true]);
        $short = Product::create(['name' => 'محصول کمبود', 'slug' => 'short-item-atomic', 'price' => 70000, 'stock' => 1, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-ATOMIC-STOCK-01',
            'customer_name' => 'مشتری اتمیک',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 240000,
            'shipping_amount' => 0,
            'total' => 240000,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $available->id, 'product_name' => $available->name, 'unit_price' => 50000, 'quantity' => 2, 'line_total' => 100000]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $short->id, 'product_name' => $short->name, 'unit_price' => 70000, 'quantity' => 2, 'line_total' => 140000]);

        $this->post('/admin/orders/'.$order->id.'/reconcile')->assertSessionHasErrors('stock');

        $this->assertDatabaseHas('products', ['id' => $available->id, 'stock' => 5]);
        $this->assertDatabaseHas('products', ['id' => $short->id, 'stock' => 1]);
        $this->assertDatabaseMissing('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid_stock_issue']);
    }

    public function test_duplicate_order_lines_are_aggregated_before_stock_deduction(): void
    {
        $admin = User::create(['name' => 'مدیر تست چندردیفه', 'mobile' => '09111111202', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $this->actingAs($admin);
        $product = Product::create(['name' => 'محصول چندردیفه', 'slug' => 'duplicate-lines-item', 'price' => 50000, 'stock' => 5, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-DUPLICATE-LINES-01',
            'customer_name' => 'مشتری چندردیفه',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'pending',
            'payment_status' => 'paid',
        ]);
        foreach ([2, 2] as $quantity) {
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 50000, 'quantity' => $quantity, 'line_total' => 100000]);
        }

        $this->post('/admin/orders/'.$order->id.'/reconcile')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'order_id' => $order->id,
            'type' => 'sale',
            'quantity' => -4,
            'previous_stock' => 5,
            'new_stock' => 1,
        ]);
        $this->assertSame(1, StockMovement::where('order_id', $order->id)->where('type', 'sale')->count());
    }

    public function test_admin_can_filter_low_stock_products_and_dashboard_counts_them(): void
    {
        $admin = User::create(['name' => 'مدیر انبار', 'mobile' => '09111111123', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        Product::create(['name' => 'موجودی کم', 'slug' => 'low-stock-product', 'price' => 100000, 'stock' => 3, 'is_active' => true]);
        Product::create(['name' => 'موجودی کافی', 'slug' => 'healthy-stock-product', 'price' => 100000, 'stock' => 12, 'is_active' => true]);
        Product::create(['name' => 'ناموجود انبار', 'slug' => 'empty-stock-product', 'price' => 100000, 'stock' => 0, 'is_active' => true]);

        $this->actingAs($admin)->get('/admin/products?stock_status=low')
            ->assertOk()
            ->assertSee('موجودی کم')
            ->assertDontSee('موجودی کافی')
            ->assertDontSee('ناموجود انبار');

        $this->get('/admin')->assertOk()->assertSee('محصولات با موجودی کم (1)');
    }

    public function test_admin_dashboard_summarizes_paid_revenue_and_unpaid_orders(): void
    {
        $admin = User::create(['name' => 'مدیر فروش', 'mobile' => '09111111124', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        Order::create(['order_number' => 'CS-REVENUE-01', 'customer_name' => 'مشتری پرداخت‌شده', 'mobile' => '09123456789', 'shipping_address' => 'تهران، نشانی نمونه', 'subtotal' => 250000, 'shipping_amount' => 0, 'total' => 250000, 'status' => 'processing', 'payment_status' => 'paid']);
        Order::create(['order_number' => 'CS-REVENUE-02', 'customer_name' => 'مشتری پرداخت‌نشده', 'mobile' => '09123456780', 'shipping_address' => 'تهران، نشانی نمونه', 'subtotal' => 90000, 'shipping_amount' => 0, 'total' => 90000, 'status' => 'pending', 'payment_status' => 'unpaid']);

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('250,000')
            ->assertSee('سفارش‌های پرداخت‌نشده');
    }


    public function test_successful_payment_with_insufficient_stock_is_flagged_without_partial_deduction(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'api.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 987655]], 200),
        ]);

        $product = Product::create(['name' => 'محصول ناموجود', 'slug' => 'insufficient-stock-product', 'price' => 100000, 'stock' => 1, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-STOCK02',
            'customer_name' => 'مشتری پرداخت‌شده',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_authority' => 'A000000000000000000000000000003',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100000, 'quantity' => 2, 'line_total' => 200000]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')
            ->assertRedirect('/')
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'paid_stock_issue']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseMissing('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
    }

    public function test_sms_failure_does_not_reverse_a_successful_payment_or_stock_deduction(): void
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
            'api.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 987656]], 200),
            'sms.test/*' => Http::response('service unavailable', 503),
        ]);

        $product = Product::create(['name' => 'محصول پیامک', 'slug' => 'sms-failure-product', 'price' => 80000, 'stock' => 4, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-SMS001',
            'customer_name' => 'مشتری پیامک',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 160000,
            'shipping_amount' => 0,
            'total' => 160000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_authority' => 'A000000000000000000000000000004',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 80000, 'quantity' => 2, 'line_total' => 160000]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'processing']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertNull($order->fresh()->sms_sent_at);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        $this->assertNull($order->fresh()->sms_sent_at);
    }

    public function test_failed_gateway_verification_never_deducts_stock(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'api.zarinpal.com/*' => Http::response(['data' => ['code' => -9]], 200),
        ]);

        $product = Product::create(['name' => 'محصول ناموفق', 'slug' => 'failed-payment-product', 'price' => 90000, 'stock' => 3, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-261126-FAIL001',
            'customer_name' => 'مشتری ناموفق',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه',
            'subtotal' => 90000,
            'shipping_amount' => 0,
            'total' => 90000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'payment_authority' => 'A000000000000000000000000000005',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 90000, 'quantity' => 1, 'line_total' => 90000]);

        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')
            ->assertRedirect('/')
            ->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'status' => 'pending']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertDatabaseMissing('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
    }


    public function test_gateway_connection_failure_shows_order_number_for_later_payment_retry(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'api.zarinpal.com/*' => Http::response(['message' => 'temporary gateway outage'], 503),
        ]);

        $product = Product::create(['name' => 'محصول خطای درگاه', 'slug' => 'gateway-outage-product', 'price' => 125000, 'stock' => 4, 'is_active' => true]);

        $response = $this->withSession(['cart' => [$product->id => 1]])->post('/checkout', [
            'customer_name' => 'مشتری درگاه',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه، پلاک ۱',
        ])->assertRedirect('/checkout');

        $order = Order::where('mobile', '09123456789')->latest()->firstOrFail();
        $response->assertSessionHasErrors('checkout');
        $this->get('/checkout')->assertOk()->assertSee($order->order_number);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'unpaid',
            'payment_authority' => null,
        ]);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
    }


    public function test_successful_gateway_payment_after_order_cancellation_is_recorded_for_refund(): void
    {
        config(['services.zarinpal.merchant_id' => 'test-merchant', 'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment']);
        Http::fake(['api.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 987657]], 200)]);
        $product = Product::create(['name' => 'محصول لغوشده', 'slug' => 'cancelled-paid-product', 'price' => 100000, 'stock' => 4, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-CANCEL-PAY-001', 'customer_name' => 'مشتری لغوشده', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، خیابان نمونه', 'subtotal' => 100000, 'shipping_amount' => 0, 'total' => 100000,
            'status' => 'cancelled', 'payment_status' => 'unpaid', 'payment_authority' => 'A000000000000000000000000000006',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 100000, 'quantity' => 1, 'line_total' => 100000]);
        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/')->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'payment_ref_id' => '987657', 'status' => 'refund_required']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 4]);
        $this->assertDatabaseMissing('stock_movements', ['order_id' => $order->id, 'type' => 'sale']);
    }

    public function test_already_verified_gateway_callback_code_101_records_cancelled_payment_for_refund(): void
    {
        config(['services.zarinpal.merchant_id' => 'test-merchant', 'services.zarinpal.base_url' => 'https://api.zarinpal.com/pg/v4/payment']);
        Http::fake(['api.zarinpal.com/*' => Http::response(['data' => ['code' => 101, 'ref_id' => 987658]], 200)]);
        $product = Product::create(['name' => 'محصول بازپرداخت', 'slug' => 'refund-required-product', 'price' => 70000, 'stock' => 3, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-REFUND-101', 'customer_name' => 'مشتری بازپرداخت', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی نمونه', 'subtotal' => 70000, 'shipping_amount' => 0, 'total' => 70000,
            'status' => 'cancelled', 'payment_status' => 'unpaid', 'payment_authority' => 'A000000000000000000000000000007',
        ]);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'product_name' => $product->name, 'unit_price' => 70000, 'quantity' => 1, 'line_total' => 70000]);
        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')->assertRedirect('/')->assertSessionHasErrors('payment');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'paid', 'status' => 'refund_required']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
    }


    public function test_admin_can_record_manual_refund_once_with_gateway_reference(): void
    {
        $admin = User::create(['name' => 'مدیر بازپرداخت', 'mobile' => '09111111203', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $order = Order::create([
            'order_number' => 'CS-REFUND-RECORD-01', 'customer_name' => 'مشتری بازپرداخت', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 180000, 'shipping_amount' => 0, 'total' => 180000,
            'status' => 'refund_required', 'payment_status' => 'paid',
        ]);
        $payload = ['refund_ref_id' => 'REF-778899', 'refund_note' => 'بازپرداخت در پنل زرین‌پال انجام شد.'];

        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/refund', $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', [
            'id' => $order->id, 'payment_status' => 'refunded', 'status' => 'cancelled',
            'refund_ref_id' => 'REF-778899', 'refund_note' => $payload['refund_note'],
        ]);
        $this->assertNotNull($order->fresh()->refunded_at);

        $this->post('/admin/orders/'.$order->id.'/refund', $payload)->assertSessionHasErrors('refund');
        $this->assertSame(1, Order::whereKey($order->id)->where('payment_status', 'refunded')->count());
    }

    public function test_admin_cannot_record_refund_without_a_paid_refund_required_order(): void
    {
        $admin = User::create(['name' => 'مدیر بازپرداخت دوم', 'mobile' => '09111111204', 'password' => 'a-secure-test-password', 'is_admin' => true]);
        $order = Order::create([
            'order_number' => 'CS-REFUND-BLOCK-01', 'customer_name' => 'مشتری پرداخت‌نشده', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 90000, 'shipping_amount' => 0, 'total' => 90000,
            'status' => 'pending', 'payment_status' => 'unpaid',
        ]);
        $this->actingAs($admin)->post('/admin/orders/'.$order->id.'/refund', [
            'refund_ref_id' => 'REF-INVALID', 'refund_note' => 'نباید قابل ثبت باشد.',
        ])->assertSessionHasErrors('refund');
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'status' => 'pending']);
    }


    public function test_repeated_payment_callback_cannot_reverse_a_recorded_refund(): void
    {
        Http::fake();
        $product = Product::create(['name' => 'محصول مستردشده', 'slug' => 'already-refunded-product', 'price' => 50000, 'stock' => 2, 'is_active' => true]);
        $order = Order::create([
            'order_number' => 'CS-REFUNDED-CALLBACK-01', 'customer_name' => 'مشتری مستردشده', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 50000, 'shipping_amount' => 0, 'total' => 50000,
            'status' => 'cancelled', 'payment_status' => 'refunded', 'payment_authority' => 'A000000000000000000000000000008',
            'refund_ref_id' => 'REF-ALREADY-DONE', 'refund_note' => 'بازپرداخت قبلاً انجام شده است.', 'refunded_at' => now(),
        ]);
        $this->get('/payment/callback?Authority='.$order->payment_authority.'&Status=OK')
            ->assertRedirect('/')
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'refunded', 'status' => 'cancelled', 'refund_ref_id' => 'REF-ALREADY-DONE']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 2]);
        Http::assertNothingSent();
    }


    public function test_zarinpal_sandbox_request_uses_sandbox_urls_and_requires_s_prefixed_authority(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'e3f4743b-58e6-4e14-b61f-dd5b71e59355',
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
            'services.zarinpal.startpay_url' => 'https://sandbox.zarinpal.com/pg/StartPay',
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response(['data' => [
                'code' => 100,
                'authority' => 'S0000000000000000000000000000001',
            ]], 200),
        ]);
        $order = Order::create([
            'order_number' => 'CS-SANDBOX-REQUEST-01',
            'customer_name' => 'مشتری سندباکس',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی آزمایش',
            'subtotal' => 125000,
            'shipping_amount' => 0,
            'total' => 125000,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $url = app(\App\Services\ZarinpalService::class)->requestPayment($order);

        $this->assertSame('https://sandbox.zarinpal.com/pg/StartPay/S0000000000000000000000000000001', $url);
        $this->assertSame('S0000000000000000000000000000001', $order->fresh()->payment_authority);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://sandbox.zarinpal.com/pg/v4/payment/request.json'
            && $request['merchant_id'] === 'e3f4743b-58e6-4e14-b61f-dd5b71e59355'
            && $request['amount'] === 1250000
        );
    }

    public function test_zarinpal_sandbox_rejects_authority_without_s_prefix(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'e3f4743b-58e6-4e14-b61f-dd5b71e59355',
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'authority' => 'A0000000000000000000000000000001']], 200),
        ]);
        $order = Order::create([
            'order_number' => 'CS-SANDBOX-BAD-AUTH-01', 'customer_name' => 'مشتری تست', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 50000, 'shipping_amount' => 0, 'total' => 50000,
            'status' => 'pending', 'payment_status' => 'unpaid',
        ]);

        try {
            app(\App\Services\ZarinpalService::class)->requestPayment($order);
            $this->fail('Sandbox authority without S prefix must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('باید با S شروع شود', $exception->getMessage());
        }

        $this->assertNull($order->fresh()->payment_authority);
    }

    public function test_zarinpal_inquiry_is_read_only_and_never_marks_order_paid(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'e3f4743b-58e6-4e14-b61f-dd5b71e59355',
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response(['data' => [
                'status' => 'PAID',
                'code' => 100,
                'message' => 'Success',
            ], 'errors' => []], 200),
        ]);
        $order = Order::create([
            'order_number' => 'CS-SANDBOX-INQUIRY-01', 'customer_name' => 'مشتری استعلام', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 80000, 'shipping_amount' => 0, 'total' => 80000,
            'status' => 'pending', 'payment_status' => 'unpaid',
            'payment_authority' => 'S0000000000000000000000000000002',
        ]);

        $result = app(\App\Services\ZarinpalService::class)->inquiry($order);

        $this->assertSame('PAID', $result['status']);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'payment_status' => 'unpaid', 'status' => 'pending']);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://sandbox.zarinpal.com/pg/v4/payment/inquiry.json'
            && $request['authority'] === $order->payment_authority
            && !array_key_exists('amount', $request->data())
        );
    }

    public function test_zarinpal_sandbox_verify_uses_verify_endpoint_and_accepts_verified_response(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'e3f4743b-58e6-4e14-b61f-dd5b71e59355',
            'services.zarinpal.base_url' => 'https://sandbox.zarinpal.com/pg/v4/payment',
        ]);
        Http::fake([
            'sandbox.zarinpal.com/*' => Http::response(['data' => ['code' => 100, 'ref_id' => 7654321]], 200),
        ]);
        $order = Order::create([
            'order_number' => 'CS-SANDBOX-VERIFY-01', 'customer_name' => 'مشتری وریفای', 'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست', 'subtotal' => 95000, 'shipping_amount' => 0, 'total' => 95000,
            'status' => 'pending', 'payment_status' => 'unpaid',
            'payment_authority' => 'S0000000000000000000000000000003',
        ]);

        $result = app(\App\Services\ZarinpalService::class)->verify($order);

        $this->assertSame(7654321, $result['ref_id']);
        Http::assertSent(fn ($request) =>
            $request->url() === 'https://sandbox.zarinpal.com/pg/v4/payment/verify.json'
            && $request['amount'] === 950000
            && $request['authority'] === $order->payment_authority
        );
    }


    public function test_regular_user_cannot_access_admin_dashboard(): void
    {
        $user = User::create([
            'name' => 'کاربر عادی',
            'mobile' => '09123456780',
            'password' => 'a-secure-test-password',
            'is_admin' => false,
        ]);

        $this->actingAs($user)->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_updating_inactive_product_removes_it_from_cart(): void
    {
        $product = Product::create([
            'name' => 'محصول غیرفعال',
            'slug' => 'inactive-cart-product',
            'price' => 120000,
            'stock' => 5,
            'is_active' => false,
        ]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->patch('/cart/'.$product->id, ['quantity' => 2])
            ->assertSessionHasErrors('cart')
            ->assertSessionHas('cart', []);

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 5]);
    }

    public function test_sitemap_excludes_inactive_products(): void
    {
        Product::create([
            'name' => 'محصول پنهان',
            'slug' => 'hidden-sitemap-product',
            'price' => 100000,
            'stock' => 1,
            'is_active' => false,
        ]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee(route('products.show', ['product' => 'hidden-sitemap-product']), false);
    }



    public function test_product_3d_viewer_keeps_an_image_fallback(): void
    {
        $product = Product::create([
            'name' => 'محصول سه‌بعدی',
            'slug' => 'three-dimensional-product',
            'price' => 200000,
            'stock' => 2,
            'image' => 'products/fallback.webp',
            'model_3d' => 'models/product.glb',
            'is_active' => true,
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('product-3d-viewer', false)
            ->assertSee('product-3d-fallback', false)
            ->assertSee('storage/products/fallback.webp', false);
    }


    public function test_paid_order_deducts_stock_and_records_movement_only_once(): void
    {
        $product = Product::create([
            'name' => 'محصول کسر موجودی',
            'slug' => 'stock-deduction-once',
            'price' => 100000,
            'stock' => 5,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CS-STOCK-ONCE-01',
            'customer_name' => 'مشتری موجودی',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100000,
            'quantity' => 2,
            'line_total' => 200000,
        ]);

        $service = app(\App\Services\OrderFulfillmentService::class);

        $this->assertTrue($service->deductStock($order));
        $this->assertTrue($service->deductStock($order->fresh()));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->assertNotNull($order->fresh()->stock_deducted_at);
        $this->assertDatabaseCount('stock_movements', 1);
    }

    public function test_paid_order_with_insufficient_stock_is_flagged_without_negative_inventory(): void
    {
        $product = Product::create([
            'name' => 'محصول کمبود موجودی',
            'slug' => 'stock-shortage-product',
            'price' => 100000,
            'stock' => 1,
            'is_active' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CS-STOCK-SHORT-01',
            'customer_name' => 'مشتری کمبود',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 200000,
            'shipping_amount' => 0,
            'total' => 200000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'unit_price' => 100000,
            'quantity' => 2,
            'line_total' => 200000,
        ]);

        $this->assertFalse(app(\App\Services\OrderFulfillmentService::class)->deductStock($order));

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 1]);
        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'paid_stock_issue', 'stock_deducted_at' => null]);
        $this->assertDatabaseCount('stock_movements', 0);
    }


    public function test_refunded_order_cannot_start_another_payment_attempt(): void
    {
        $order = Order::create([
            'order_number' => 'CS-REFUNDED-RETRY-01',
            'customer_name' => 'مشتری بازپرداخت',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'refunded',
            'payment_status' => 'refunded',
        ]);

        $this->post(route('orders.pay.retry', $order), [
            'order_number' => $order->order_number,
            'mobile' => $order->mobile,
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'payment_status' => 'refunded',
            'payment_authority' => null,
        ]);
    }

    public function test_paid_stock_issue_order_cannot_start_another_payment_attempt(): void
    {
        $order = Order::create([
            'order_number' => 'CS-STOCK-ISSUE-RETRY-01',
            'customer_name' => 'مشتری بررسی موجودی',
            'mobile' => '09123456789',
            'shipping_address' => 'تهران، نشانی تست',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'paid_stock_issue',
            'payment_status' => 'paid',
        ]);

        $this->post(route('orders.pay.retry', $order), [
            'order_number' => $order->order_number,
            'mobile' => $order->mobile,
        ])->assertSessionHasErrors('payment');

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid_stock_issue',
            'payment_status' => 'paid',
            'payment_authority' => null,
        ]);
    }


}
