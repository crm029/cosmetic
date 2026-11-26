<?php
namespace App\Services;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use RuntimeException;
class ZarinpalService {
 private function baseUrl(): string { return rtrim((string)config('services.zarinpal.base_url','https://api.zarinpal.com/pg/v4/payment'),' /'); }
 public function requestPayment(Order $order): string {
  $merchant=env('ZARINPAL_MERCHANT_ID');
  if(!$merchant)throw new RuntimeException('درگاه پرداخت هنوز توسط مدیر فروشگاه پیکربندی نشده است.');
  $response=Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/request.json',[
   'merchant_id'=>$merchant,'amount'=>$order->total*10,'description'=>'پرداخت سفارش '.$order->order_number,
   'callback_url'=>route('payment.callback'),'metadata'=>['mobile'=>$order->mobile,'email'=>$order->email]
  ])->throw()->json();
  if(($response['data']['code']??0)!==100||empty($response['data']['authority']))throw new RuntimeException($response['errors']['message']??'دریافت لینک پرداخت ناموفق بود.');
  $order->update(['payment_authority'=>$response['data']['authority']]);
  return 'https://www.zarinpal.com/pg/StartPay/'.$response['data']['authority'];
 }
 public function verify(Order $order): bool {
  $merchant=env('ZARINPAL_MERCHANT_ID');if(!$merchant||!$order->payment_authority)return false;
  $response=Http::acceptJson()->asJson()->timeout(20)->post($this->baseUrl().'/verify.json',[
   'merchant_id'=>$merchant,'amount'=>$order->total*10,'authority'=>$order->payment_authority
  ])->throw()->json();
  $code=$response['data']['code']??0;
  if(in_array($code,[100,101],true)){
   $order->update(['payment_status'=>'paid','status'=>'processing','payment_ref_id'=>(string)($response['data']['ref_id']??'')]);
   return true;
  }
  return false;
 }
}