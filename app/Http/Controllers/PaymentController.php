<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\ZarinpalService;
use App\Services\SmsService;
use App\Services\OrderFulfillmentService;
use Illuminate\Http\Request;
class PaymentController extends Controller {
 public function callback(Request $request,ZarinpalService $gateway,SmsService $sms,OrderFulfillmentService $fulfillment) {
  $authority=(string)$request->query('Authority','');$status=(string)$request->query('Status','');
  $order=Order::where('payment_authority',$authority)->first();
  if(!$order)return redirect()->route('home')->withErrors(['payment'=>'سفارش پرداخت‌شده پیدا نشد.']);
  if($order->status==='cancelled')return redirect()->route('home')->withErrors(['payment'=>'این سفارش لغو شده و امکان تکمیل پرداخت آن وجود ندارد.']);
  if(strtoupper($status)!=='OK')return redirect()->route('home')->withErrors(['payment'=>'پرداخت توسط کاربر لغو شد یا ناموفق بود.']);
  try {
   if($order->payment_status!=='paid'&&!$gateway->verify($order))return redirect()->route('home')->withErrors(['payment'=>'تأیید پرداخت از سوی درگاه انجام نشد.']);
   $settled=$fulfillment->deductStock($order);
   if(!$settled)return redirect()->route('home')->withErrors(['payment'=>'پرداخت موفق بود، اما موجودی نیاز به بررسی دستی دارد. با پشتیبانی فروشگاه تماس بگیرید.']);
   if(!$order->fresh()->sms_sent_at && $sms->sendOrderPaid($order->fresh()))$order->update(['sms_sent_at'=>now()]);
   session()->forget('cart');
   return redirect()->route('home')->with('success','پرداخت با موفقیت تأیید شد. شماره سفارش: '.$order->order_number);
  } catch(\Throwable $e) {
   report($e);return redirect()->route('home')->withErrors(['payment'=>'پرداخت ثبت شده است، اما بررسی نهایی سفارش با خطا روبه‌رو شد.']);
  }
 }
}