<?php
namespace App\Services;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
class SmsService {
 private function send(Order $order,string $message): bool {
  $url=config('services.0098sms.base_url');$username=config('services.0098sms.username');$password=config('services.0098sms.password');$sender=config('services.0098sms.sender');$domain=config('services.0098sms.domain','0098');
  if(!$url||!$username||!$password||!$sender||!$domain)return false;
  if(!str_starts_with($url,'https://')){Log::warning('0098SMS endpoint must use HTTPS.');return false;}
  try {
   $response=Http::timeout(15)->get($url,['FROM'=>$sender,'TO'=>$order->mobile,'TEXT'=>$message,'USERNAME'=>$username,'PASSWORD'=>$password,'DOMAIN'=>$domain]);
   if(!$response->successful()){Log::warning('0098SMS HTTP request failed',['status'=>$response->status()]);return false;}
   $body=trim($response->body());
   if($body!=='0'){Log::warning('0098SMS returned an unrecognized or unsuccessful response',['response'=>$body]);return false;}
   return true;
  } catch(\Throwable $e) {
   Log::warning('0098SMS request failed',['exception'=>$e::class]);return false;
  }
 }
 private function dispatchOnce(Order $order,string $sentColumn,string $claimedColumn,callable $send): bool {
  $claimed=DB::transaction(function()use($order,$sentColumn,$claimedColumn){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->{$sentColumn})return false;
   $claim=$locked->{$claimedColumn};
   if($claim && $claim->gt(now()->subMinutes(10)))return false;
   $locked->forceFill([$claimedColumn=>now()])->save();
   return true;
  });
  if(!$claimed)return false;
  $sent=(bool)$send();
  DB::transaction(function()use($order,$sentColumn,$claimedColumn,$sent){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($sent)$locked->forceFill([$sentColumn=>now(),$claimedColumn=>null])->save();
   else $locked->forceFill([$claimedColumn=>null])->save();
  });
  return $sent;
 }
 public function sendOrderPaidOnce(Order $order): bool {
  return $this->dispatchOnce($order,'sms_sent_at','sms_claimed_at',fn()=>$this->sendOrderPaid($order));
 }
 public function sendShipmentUpdateOnce(Order $order): bool {
  return $this->dispatchOnce($order,'shipment_sms_sent_at','shipment_sms_claimed_at',fn()=>$this->sendShipmentUpdate($order));
 }
 public function sendDeliveryUpdateOnce(Order $order): bool {\n  return $this->dispatchOnce($order,'delivery_sms_sent_at','delivery_sms_claimed_at',fn()=>$this->sendDeliveryUpdate($order));\n }\n public function sendDeliveryUpdate(Order $order): bool {\n  return $this->send($order,'مشتری گرامی، سفارش '.$order->order_number.' با موفقیت تحویل شما شد. از خرید شما سپاسگزاریم.');\n }\n public function sendOrderPaid(Order $order): bool {
  return $this->send($order,'مشتری گرامی، پرداخت سفارش '.$order->order_number.' با مبلغ '.number_format($order->total).' تومان با موفقیت ثبت شد. از خرید شما سپاسگزاریم.');
 }
 public function sendShipmentUpdate(Order $order): bool {
  $message='مشتری گرامی، سفارش '.$order->order_number.' ارسال شد.';
  if($order->shipping_carrier)$message.=' روش ارسال: '.$order->shipping_carrier.'.';
  if($order->tracking_code)$message.=' کد رهگیری: '.$order->tracking_code.'.';
  return $this->send($order,$message);
 }
}
