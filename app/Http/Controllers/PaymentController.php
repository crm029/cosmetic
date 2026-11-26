<?php
namespace App\\Http\\Controllers;
use App\\Models\\Order;
use App\\Services\\ZarinpalService;
use Illuminate\\Http\\Request;
use Illuminate\\Support\\Facades\\DB;
class PaymentController extends Controller {
 public function callback(Request $request,ZarinpalService $gateway) {
  $authority=(string)$request->query('Authority','');$status=(string)$request->query('Status','');
  $order=Order::where('payment_authority',$authority)->first();
  if(!$order)return redirect()->route('home')->withErrors(['payment'=>'سفارش پرداخت‌شده پیدا نشد.']);
  if($order->payment_status==='paid')return redirect()->route('home')->with('success','پرداخت این سفارش قبلاً تأیید شده است.');
  if(strtoupper($status)!=='OK'||!$gateway->verify($order))return redirect()->route('home')->withErrors(['payment'=>'پرداخت ناموفق یا توسط کاربر لغو شد.']);
  DB::transaction(function()use($order){
   $locked=Order::whereKey($order->id)->lockForUpdate()->first();
   if($locked->payment_status!=='paid')return;
   foreach($locked->items as $item){$product=\\App\\Models\\Product::whereKey($item->product_id)->lockForUpdate()->first();if($product){$product->decrement('stock',$item->quantity);}}
  });
  session()->forget('cart');
  return redirect()->route('home')->with('success','پرداخت با موفقیت تأیید شد. شماره سفارش: '.$order->order_number);
 }
}