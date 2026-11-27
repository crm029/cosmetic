<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Services\ZarinpalService;
use App\Services\StockReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class OrderTrackingController extends Controller {
 public function index(): View { return view('orders.track',['order'=>null]); }
 public function lookup(Request $request): View|RedirectResponse {
  $data=$request->validate(['order_number'=>['required','string','max:40'],'mobile'=>['required','regex:/^09[0-9]{9}$/']]);
  $order=Order::with('items')->where('order_number',strtoupper(trim($data['order_number'])))->where('mobile',$data['mobile'])->first();
  if(!$order)return back()->withErrors(['tracking'=>'سفارشی با این شماره و موبایل پیدا نشد.'])->withInput();
  return view('orders.track',compact('order'));
 }
 public function retryPayment(Request $request,Order $order,ZarinpalService $gateway,StockReservationService $reservations): RedirectResponse {
  $data=$request->validate(['order_number'=>['required','string','max:40'],'mobile'=>['required','regex:/^09[0-9]{9}$/']]);
  if(!hash_equals($order->order_number,strtoupper(trim($data['order_number'])))||!hash_equals($order->mobile,$data['mobile']))abort(404);
  if(in_array($order->payment_status,['paid','refunded'],true)||in_array($order->status,['cancelled','refund_required','paid_stock_issue'],true))return back()->withErrors(['payment'=>'این سفارش امکان پرداخت مجدد ندارد.']);
  try {
   DB::transaction(function()use($order,$reservations){$locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();if(in_array($locked->payment_status,['paid','refunded'],true)||in_array($locked->status,['cancelled','refund_required','paid_stock_issue'],true))throw new \RuntimeException('این سفارش امکان پرداخت مجدد ندارد.');$reservations->reserveForOrder($locked);});
   return redirect()->away($gateway->requestPayment($order->fresh()));
  } catch(\Throwable $e) {
   report($e);
   return back()->withErrors(['payment'=>$e instanceof \RuntimeException?$e->getMessage():'ساخت لینک پرداخت ناموفق بود. کمی بعد دوباره تلاش کنید.']);
  }
 }
}
