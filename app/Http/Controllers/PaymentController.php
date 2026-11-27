<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\ZarinpalService;
use App\Services\SmsService;
use App\Services\OrderFulfillmentService;
use App\Services\StockReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PaymentController extends Controller {
 public function callback(Request $request,ZarinpalService $gateway,SmsService $sms,OrderFulfillmentService $fulfillment,StockReservationService $reservations) {
  $authority=(string)$request->query('Authority','');$status=(string)$request->query('Status','');
  $order=Order::where('payment_authority',$authority)->first();
  if(!$order)return redirect()->route('home')->withErrors(['payment'=>'سفارش پرداخت‌شده پیدا نشد.']);
  if(strtoupper($status)!=='OK'){
   DB::transaction(function()use($order,$reservations){$locked=Order::whereKey($order->id)->lockForUpdate()->first();if($locked&&$locked->payment_status!=='paid')$reservations->releaseForOrder($locked);});
   return redirect()->route('home')->withErrors(['payment'=>'پرداخت توسط کاربر لغو شد یا ناموفق بود.']);
  }
  try {
   $verifiedData=null;
   if(!in_array($order->payment_status,['paid','refunded'],true)) {
    $verifiedData=$gateway->verify($order);
    if($verifiedData===null)return redirect()->route('home')->withErrors(['payment'=>'تأیید پرداخت از سوی درگاه انجام نشد.']);
   }
   $settlement=DB::transaction(function()use($order,$verifiedData){
    $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    if(!in_array($locked->payment_status,['paid','refunded'],true)) {
     $changes=['payment_status'=>'paid','payment_ref_id'=>(string)($verifiedData['ref_id']??'')];
     if($locked->status==='cancelled')$changes['status']='refund_required';
     elseif($locked->status==='pending')$changes['status']='processing';
     $locked->update($changes);
    }
    $locked->refresh();
    return ['status'=>$locked->status,'paid'=>in_array($locked->payment_status,['paid','refunded'],true),'payment_status'=>$locked->payment_status];
   });
   if(!$settlement['paid'])return redirect()->route('home')->withErrors(['payment'=>'وضعیت پرداخت نیازمند بررسی پشتیبانی است.']);
   if($settlement['payment_status']==='refunded')return redirect()->route('home')->with('success','این سفارش قبلاً بازپرداخت شده است.');
   if($settlement['status']==='refund_required')return redirect()->route('home')->withErrors(['payment'=>'پرداخت موفق ثبت شد؛ اما سفارش قبلاً لغو شده بود. بازپرداخت وجه نیازمند بررسی پشتیبانی است.']);
   if(!$fulfillment->deductStock($order->fresh()))return redirect()->route('home')->withErrors(['payment'=>'پرداخت موفق بود، اما موجودی رزروشده منقضی شده یا نیاز به بررسی دستی دارد. با پشتیبانی فروشگاه تماس بگیرید.']);
   $sms->sendOrderPaidOnce($order->fresh());
   session()->forget('cart');
   return redirect()->route('home')->with('success','پرداخت با موفقیت تأیید شد. شماره سفارش: '.$order->order_number);
  } catch(\Throwable $e) {
   report($e);
   return redirect()->route('home')->withErrors(['payment'=>'بررسی پرداخت با خطا روبه‌رو شد. در صورت کسر وجه، شماره سفارش را برای پشتیبانی نگه دارید: '.$order->order_number]);
  }
 }
}
