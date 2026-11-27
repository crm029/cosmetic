<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OrderDeliveryNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_confirmation_sms_is_sent_only_once(): void
    {
        config(['services.0098sms' => [
            'base_url' => 'https://sms.example.test/send',
            'username' => 'test-user',
            'password' => 'test-password',
            'sender' => '1000',
            'domain' => '0098',
        ]]);
        Http::fake(['sms.example.test/*' => Http::response('0', 200)]);

        $order = Order::create([
            'order_number' => 'CS-DELIVERY-SMS-001',
            'customer_name' => 'مشتری پیامکی',
            'mobile' => '09123456780',
            'shipping_address' => 'تهران، نشانی آزمایشی معتبر',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $service = app(SmsService::class);

        $this->assertTrue($service->sendDeliveryUpdateOnce($order));
        $this->assertFalse($service->sendDeliveryUpdateOnce($order->fresh()));
        Http::assertSentCount(1);
        $this->assertNotNull($order->fresh()->delivery_sms_sent_at);
        $this->assertNull($order->fresh()->delivery_sms_claimed_at);
    }
    public function test_admin_can_retry_a_failed_delivery_sms_without_duplicate_successful_messages(): void
    {
        config(['services.0098sms' => [
            'base_url' => 'https://sms.example.test/send',
            'username' => 'test-user',
            'password' => 'test-password',
            'sender' => '1000',
            'domain' => '0098',
        ]]);
        Http::fake([
            'sms.example.test/*' => Http::sequence()
                ->push('temporary provider error', 200)
                ->push('0', 200),
        ]);

        $admin = \\App\\Models\\User::create([
            'name' => 'مدیر پیامک',
            'mobile' => '09123456781',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CS-DELIVERY-SMS-RETRY',
            'customer_name' => 'مشتری پیامکی',
            'mobile' => '09123456780',
            'shipping_address' => 'تهران، نشانی آزمایشی معتبر',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'delivered',
            'payment_status' => 'paid',
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.orders.sms.retry', [$order, 'delivery']))
            ->assertSessionHasErrors('sms');
        $this->assertNull($order->fresh()->delivery_sms_sent_at);

        $this->actingAsAdmin($admin)
            ->post(route('admin.orders.sms.retry', [$order, 'delivery']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
        $this->assertNotNull($order->fresh()->delivery_sms_sent_at);
        $this->assertNull($order->fresh()->delivery_sms_claimed_at);

        $this->actingAsAdmin($admin)
            ->post(route('admin.orders.sms.retry', [$order, 'delivery']))
            ->assertSessionHas('success');
        Http::assertSentCount(2);
    }

    public function test_admin_cannot_retry_a_shipment_sms_before_order_is_shipped(): void
    {
        $admin = \\App\\Models\\User::create([
            'name' => 'مدیر پیامک',
            'mobile' => '09123456782',
            'password' => 'a-secure-test-password',
            'is_admin' => true,
        ]);
        $order = Order::create([
            'order_number' => 'CS-DELIVERY-SMS-GUARD',
            'customer_name' => 'مشتری پیامکی',
            'mobile' => '09123456780',
            'shipping_address' => 'تهران، نشانی آزمایشی معتبر',
            'subtotal' => 100000,
            'shipping_amount' => 0,
            'total' => 100000,
            'status' => 'processing',
            'payment_status' => 'paid',
        ]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.orders.sms.retry', [$order, 'shipment']))
            ->assertSessionHasErrors('sms');
        Http::assertNothingSent();
    }

}
