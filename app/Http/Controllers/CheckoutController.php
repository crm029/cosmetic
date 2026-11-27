<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\ZarinpalService;
use App\Services\StockReservationService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
class CheckoutController extends Controller {
 public function index(Request $request): View|RedirectResponse {
  $cart=$request->session()->get('cart',[]);
  if(empty($cart))return redirect()->route('cart.index');
  $products=Product::whereIn('id',array_keys($cart))->where('is_active',true)->get();$subtotal=0;$validCart=[];
  foreach($products as $p){
   $quantity=(int)($cart[$p->id]??0);
   if($quantity<1)continue;
   if($quantity>20)return redirect()->route('cart.index')->withErrors(['cart'=>'حداکثر تعداد هر محصول در سبد خرید ۲۰ عدد است.']);
   $reserved=(int)DB::table('stock_reservations')->where('product_id',$p->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->sum('quantity');
   if($quantity>$p->stock-$reserved)return redirect()->route('cart.index')->withErrors(['cart'=>'موجودی آزاد '.$p->name.' برای تعداد انتخاب‌شده کافی نیست.']);
   $validCart[$p->id]=$quantity;$subtotal+=$p->price*$quantity;
  }
  if(count($validCart)!==count($cart))$request->session()->put('cart',$validCart);
  $cart=$validCart;
  if(empty($cart))return redirect()->route('cart.index')->withErrors(['cart'=>'محصولات سبد خرید دیگر در دسترس نیستند.']);
  $settings=StoreSetting::current();$fee=$settings->shipping_fee_toman;$threshold=$settings->free_shipping_threshold_toman;
  $shippingMethod=request()->query('shipping_method','post')==='pickup'?'pickup':'post';
  $shipping=$shippingMethod==='pickup'?0:(($threshold>0&&$subtotal>=$threshold)?0:$fee);
  $coupon=\App\Models\Coupon::where('code',$request->session()->get('coupon_code'))->first();
  $discount=$coupon&&$coupon->isAvailable()?$coupon->discountFor($subtotal):0;
  $total=max(0,$subtotal+$shipping-$discount);
  return view('checkout.index',compact('products','cart','subtotal','shipping','total','coupon','discount','shippingMethod','settings'));
 }
 public function store(Request $request,ZarinpalService $gateway,StockReservationService $reservations): RedirectResponse {
  $data=$request->validate(['customer_name'=>['required','string','max:120'],'mobile'=>['required','regex:/^09[0-9]{9}$/'],'email'=>['nullable','email','max:190'],'shipping_address'=>['required','string','min:10','max:1500'],'notes'=>['nullable','string','max:1000'],'shipping_method'=>['required','in:post,pickup']]);
  $cart=$request->session()->get('cart',[]);if(!$cart)return redirect()->route('cart.index');
  if(!is_array($cart)||count($cart)>50)return redirect()->route('cart.index')->withErrors(['cart'=>'سبد خرید معتبر نیست؛ لطفاً دوباره محصولات را انتخاب کنید.']);
  foreach($cart as $quantity){if(!is_numeric($quantity)||(int)$quantity<1||(int)$quantity>20)return redirect()->route('cart.index')->withErrors(['cart'=>'تعداد هر محصول باید بین ۱ تا ۲۰ عدد باشد.']);}
  $order=null;$couponCode=$request->session()->get('coupon_code');
  try {
   $order=DB::transaction(function()use($cart,$data,$reservations,$couponCode){
    $products=Product::whereIn('id',array_keys($cart))->where('is_active',true)->orderBy('id')->lockForUpdate()->get();
    if($products->count()!==count($cart))throw new RuntimeException('یکی از محصولات دیگر در دسترس نیست.');
    $subtotal=0;
    foreach($products as $p){
     $qty=(int)$cart[$p->id];
     $reserved=(int)DB::table('stock_reservations')->where('product_id',$p->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->sum('quantity');
     if($qty<1||$qty>20||$qty>$p->stock-$reserved)throw new RuntimeException('موجودی آزاد '.$p->name.' کافی نیست.');
     $subtotal+=$p->price*$qty;
    }
    $settings=StoreSetting::current();$fee=$settings->shipping_fee_toman;$threshold=$settings->free_shipping_threshold_toman;
    $shipping=$data['shipping_method']==='pickup'?0:(($threshold>0&&$subtotal>=$threshold)?0:$fee);
    $coupon=null;$discount=0;
    if($couponCode){
     $coupon=\App\Models\Coupon::where('code',$couponCode)->lockForUpdate()->first();
     if(!$coupon||!$coupon->isAvailable())throw new RuntimeException('کد تخفیف دیگر معتبر نیست؛ لطفاً آن را دوباره بررسی کنید.');
     $discount=$coupon->discountFor($subtotal);
     if($discount<1)throw new RuntimeException('حداقل مبلغ خرید برای استفاده از کد تخفیف فراهم نیست.');
     $coupon->increment('used_count');
    }
    $total=max(0,$subtotal+$shipping-$discount);
    $created=Order::create(['coupon_id'=>$coupon?->id,'coupon_code'=>$coupon?->code,'discount_amount'=>$discount,'shipping_method'=>$data['shipping_method'],'order_number'=>'CS-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),'customer_name'=>$data['customer_name'],'mobile'=>$data['mobile'],'email'=>$data['email']??null,'shipping_address'=>$data['shipping_address'],'subtotal'=>$subtotal,'shipping_amount'=>$shipping,'total'=>$total,'status'=>'pending','payment_status'=>'unpaid','notes'=>$data['notes']??null]);
    foreach($products as $p){$qty=(int)$cart[$p->id];OrderItem::create(['order_id'=>$created->id,'product_id'=>$p->id,'product_name'=>$p->name,'unit_price'=>$p->price,'quantity'=>$qty,'line_total'=>$p->price*$qty]);}
    OrderStatusHistory::create(['order_id'=>$created->id,'from_status'=>null,'to_status'=>'pending','actor_type'=>'customer','note'=>'سفارش توسط مشتری ثبت شد.']);
    $reservations->reserveForOrder($created);
    return $created;
   });
   $request->session()->forget('coupon_code');
   return redirect()->away($gateway->requestPayment($order));
  } catch (RuntimeException $e) {
   $suffix=$order?' شماره سفارش: '.$order->order_number:'';
   return redirect()->route('checkout.index')->withErrors(['checkout'=>$e->getMessage().$suffix]);
  } catch (\Throwable $e) {
   report($e);$suffix=$order?' شماره سفارش شما '.$order->order_number.' است؛ برای پرداخت مجدد از صفحه پیگیری سفارش استفاده کنید.':'';
   return redirect()->route('checkout.index')->withErrors(['checkout'=>'ارتباط با درگاه یا ثبت سفارش با خطا روبه‌رو شد. لطفاً دوباره تلاش کنید.'.$suffix]);
  }
 }
}
