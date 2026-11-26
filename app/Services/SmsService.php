<?php
namespace App\Services;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
class SmsService {
 public function sendOrderPaid(Order $order): bool {
  $url=config('services.0098sms.base_url');
  $username=config('services.0098sms.username');
  $password=config('services.0098sms.password');
  $sender=config('services.0098sms.sender');
  if(!$url||!$username||!$password||!$sender)return false;
  if(!str_starts_with($url,'https://')){Log::warning('0098SMS endpoint must use HTTPS.');return false;}
  $message='مشتری گرامی، پرداخت سفارش '.$order->order_number.' با مبلغ '.number_format($order->total).' تومان با موفقیت ثبت شد. از خرید شما سپاسگزاریم.';
  try {
   $response=Http::timeout(15)->get($url,[
    'username'=>$username,'password'=>$password,'from'=>$sender,'to'=>$order->mobile,'text'=>$message,
   ]);
   if(!$response->successful()) { Log::warning('0098SMS HTTP request failed',['status'=>$response->status()]);return false; }
   $body=trim($response->body());
   if($body===''||$body==='0'||str_starts_with($body,'-')){Log::warning('0098SMS returned a non-success response',['response'=>$body]);return false;}
   return true;
  } catch(\Throwable $e) {
   Log::warning('0098SMS request failed',['exception'=>$e::class]);return false;
  }
 }
}