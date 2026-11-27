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
}
