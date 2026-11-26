<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
class CheckoutController extends Controller {
 public function index(Request $request): View {
  $cart=$request->session()->get('cart',[]);abort_if(empty($cart),redirect()->route('cart.index'));
  $products=Product::whereIn('id',array_keys($cart))->where('is_active',true)->get();$subtotal=0;
  foreach($products as $p)$subtotal+=$p->price*(int)$cart[$p->id];
  $fee=(int)config('store.shipping_fee_toman',0);$threshold=(int)config('store.free_shipping_threshold_toman',0);
  $shipping=($threshold>0&&$subtotal>=$threshold)?0:$fee;$total=$subtotal+$shipping;
  return view('checkout.index',compact('products','cart','subtotal','shipping','total'));
 }
 public function store(Request $request,ZarinpalService $gateway) {
  $data=$request->validate(['customer_name'=>['required','string','max:120'],'mobile'=>['required','regex:/^09[0-9]{9}$/'],'email'=>['nullable','email','max:190'],'shipping_address'=>['required','string','min:10','max:1500'],'notes'=>['nullable','string','max:1000']]);
  $cart=$request->session()->get('cart',[]);if(!$cart)return redirect()->route('cart.index');
  $order=null;
  try {
   $order=DB::transaction(function()use($cart,$data){
    $products=Product::whereIn('id',array_keys($cart))->where('is_active',true)->lockForUpdate()->get();
    if($products->count()!==count($cart))throw new RuntimeException('یکی از محصولات دیگر در دسترس نیست.');
    $subtotal=0;
    foreach($products as $p){$qty=(int)$cart[$p->id];if($qty<1||$qty>$p->stock)throw new RuntimeException('موجودی '.$p->name.' کافی نیست.');$subtotal+=$p->price*$qty;}
    $fee=(int)config('store.shipping_fee_toman',0);$threshold=(int)config('store.free_shipping_threshold_toman',0);
    $shipping=($threshold>0&&$subtotal>=$threshold)?0:$fee;$total=$subtotal+$shipping;
    $created=Order::create(['order_number'=>'CS-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),'customer_name'=>$data['customer_name'],'mobile'=>$data['mobile'],'email'=>$data['email']??null,'shipping_address'=>$data['shipping_address'],'subtotal'=>$subtotal,'shipping_amount'=>$shipping,'total'=>$total,'status'=>'pending','payment_status'=>'unpaid','notes'=>$data['notes']??null]);
    foreach($products as $p){$qty=(int)$cart[$p->id];OrderItem::create(['order_id'=>$created->id,'product_id'=>$p->id,'product_name'=>$p->name,'unit_price'=>$p->price,'quantity'=>$qty,'line_total'=>$p->price*$qty]);}
    return $created;
   });
   return redirect()->away($gateway->requestPayment($order));
  } catch (RuntimeException $e) {
   $suffix=$order?' شماره سفارش: '.$order->order_number:'';
   return redirect()->route('checkout.index')->withErrors(['checkout'=>$e->getMessage().$suffix]);
  } catch (\Throwable $e) {
   report($e);return redirect()->route('checkout.index')->withErrors(['checkout'=>'پرداخت یا ثبت سفارش با خطا روبه‌رو شد. لطفاً دوباره تلاش کنید.']);
  }
 }
}