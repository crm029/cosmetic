<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\Product;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class PaymentController extends Controller {
 public function callback(Request $request,ZarinpalService $gateway) {
  $authority=(string)$request->query('Authority','');$status=(string)$request->query('Status','');
  $order=Order::where('payment_authority',$authority)->first();
  if(!$order)return redirect()->route('home')->withErrors(['payment'=>'سفارش پرداخت‌شده پیدا نشد.']);
  if(strtoupper($status)!=='OK')return redirect()->route('home')->withErrors(['payment'=>'پرداخت توسط کاربر لغو شد یا ناموفق بود.']);
  try {
   if($order->payment_status!=='paid'&&!$gateway->verify($order))return redirect()->route('home')->withErrors(['payment'=>'تأیید پرداخت از سوی درگاه انجام نشد.']);
   $settled=DB::transaction(function()use($order){
    $locked=Order::whereKey($order->id)->lockForUpdate()->first();
    if($locked->stock_deducted_at)return true;
    foreach($locked->items as $item){
     $product=Product::whereKey($item->product_id)->lockForUpdate()->first();
     if(!$product||$product->stock<$item->quantity){$locked->update(['status'=>'paid_stock_issue']);return false;}
    }
    foreach($locked->items as $item){Product::whereKey($item->product_id)->decrement('stock',$item->quantity);}
    $locked->update(['stock_deducted_at'=>now(),'status'=>'processing']);
    return true;
   });
   if(!$settled)return redirect()->route('home')->withErrors(['payment'=>'پرداخت موفق بود، اما موجودی نیاز به بررسی دستی دارد. با پشتیبانی فروشگاه تماس بگیرید.']);
   session()->forget('cart');
   return redirect()->route('home')->with('success','پرداخت با موفقیت تأیید شد. شماره سفارش: '.$order->order_number);
  } catch(\Throwable $e) {
   report($e);return redirect()->route('home')->withErrors(['payment'=>'پرداخت ثبت شده است، اما بررسی نهایی سفارش با خطا روبه‌رو شد.']);
  }
 }
}