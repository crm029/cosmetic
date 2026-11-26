<?php
namespace App\\Services;
use App\\Models\\Order;
use Illuminate\\Support\\Facades\\Http;
use RuntimeException;
class ZarinpalService {
 private function baseUrl(): string { return rtrim((string)config('services.zarinpal.base_url','https://api.zarinpal.com/pg/v4/payment'),' /'); }
 public function requestPayment(Order $order): string {
  $merchant=config('services.zarinpal.merchant_id');
  if(!$merchant)throw new RuntimeException('درگاه پرداخت هنوز توسط مدیر فروشگاه پیکربندی نشده است.');
  $response=Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/request.json',[
   'merchant_id'=>$merchant,'amount'=>$order->total*10,'description'=>'پرداخت سفارش '.$order->order_number,
   'callback_url'=>route('payment.callback'),'metadata'=>['mobile'=>$order->mobile,'email'=>$order->email]
  ])->throw()->json();
  if(($response['data']['code']??0)!==100||empty($response['data']['authority']))throw new RuntimeException($response['errors']['message']??'دریافت لینک پرداخت ناموفق بود.');
  $order->update(['payment_authority'=>$response['data']['authority']]);
  return rtrim((string)config('services.zarinpal.startpay_url','https://www.zarinpal.com/pg/StartPay'),'/').'/'.$response['data']['authority'];
 }
 /**
  * Verify remotely and return the gateway's verified data.
  * Database state is deliberately changed by the callback under an order row lock,
  * so a concurrent cancellation cannot overwrite a successful payment.
  */
 public function verify(Order $order): ?array {
  $merchant=config('services.zarinpal.merchant_id');if(!$merchant||!$order->payment_authority)return null;
  $response=Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/verify.json',[
   'merchant_id'=>$merchant,'amount'=>$order->total*10,'authority'=>$order->payment_authority
  ])->throw()->json();
  $data=$response['data']??[];
  $code=(int)($data['code']??0);
  if(in_array($code,[100,101],true))return $data;
  return null;
 }
}