<?php
namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ZarinpalService
{
    private function baseUrl(): string
    {
        return rtrim((string) config('services.zarinpal.base_url', 'https://sandbox.zarinpal.com/pg/v4/payment'), ' /');
    }

    private function merchantId(): string
    {
        $merchant = (string) config('services.zarinpal.merchant_id', '');
        if ($merchant === '') {
            throw new RuntimeException('درگاه پرداخت هنوز توسط مدیر فروشگاه پیکربندی نشده است.');
        }

        return $merchant;
    }

    private function isSandbox(): bool
    {
        return str_contains((string) parse_url($this->baseUrl(), PHP_URL_HOST), 'sandbox.zarinpal.com');
    }

    public function requestPayment(Order $order): string
    {
        $response = Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/request.json', [
            'merchant_id' => $this->merchantId(),
            'amount' => $order->total * 10,
            'description' => 'پرداخت سفارش '.$order->order_number,
            'callback_url' => route('payment.callback'),
            'metadata' => ['mobile' => $order->mobile, 'email' => $order->email],
        ])->throw()->json();

        $authority = (string) ($response['data']['authority'] ?? '');
        if ((int) ($response['data']['code'] ?? 0) !== 100 || $authority === '') {
            throw new RuntimeException($response['errors']['message'] ?? 'دریافت لینک پرداخت ناموفق بود.');
        }

        if ($this->isSandbox() && !str_starts_with($authority, 'S')) {
            throw new RuntimeException('پاسخ سندباکس زرین‌پال معتبر نیست؛ شناسه تراکنش آزمایشی باید با S شروع شود.');
        }

        $order->update(['payment_authority' => $authority]);

        $startPay = rtrim((string) config('services.zarinpal.startpay_url', 'https://sandbox.zarinpal.com/pg/StartPay'), '/');

        return $startPay.'/'.$authority;
    }

    /**
     * Verify is the only operation used to confirm payment.
     * Inquiry is informational and must never be used as payment verification.
     */
    public function verify(Order $order): ?array
    {
        if (!$order->payment_authority) {
            return null;
        }

        $response = Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/verify.json', [
            'merchant_id' => $this->merchantId(),
            'amount' => $order->total * 10,
            'authority' => $order->payment_authority,
        ])->throw()->json();

        $data = $response['data'] ?? [];

        return in_array((int) ($data['code'] ?? 0), [100, 101], true) ? $data : null;
    }

    /**
     * Read-only transaction status inquiry.
     * This result must not be used to mark an order as paid.
     */
    public function inquiry(Order $order): ?array
    {
        if (!$order->payment_authority) {
            return null;
        }

        $response = Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/inquiry.json', [
            'merchant_id' => $this->merchantId(),
            'authority' => $order->payment_authority,
        ])->throw()->json();

        $data = $response['data'] ?? [];

        if (!isset($data['status']) || !is_string($data['status'])) {
            return null;
        }

        return $data;
    }
}
