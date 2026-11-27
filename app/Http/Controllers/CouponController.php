<?php
namespace App\Http\Controllers;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
class CouponController extends Controller {
 public function apply(Request $request): RedirectResponse {
  $data=$request->validate(['code'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/']]);
  $coupon=Coupon::where('code',strtoupper(trim($data['code'])))->first();
  if(!$coupon||!$coupon->isAvailable())return back()->withErrors(['coupon'=>'کد تخفیف معتبر نیست یا مهلت استفاده از آن به پایان رسیده است.']);
  $cart=$request->session()->get('cart',[]);
  $products=\App\Models\Product::whereIn('id',array_keys($cart))->where('is_active',true)->get();
  $subtotal=$products->sum(fn($p)=>(int)$p->price*(int)($cart[$p->id]??0));
  if($coupon->discountFor($subtotal)<=0)return back()->withErrors(['coupon'=>'شرایط حداقل خرید این کد تخفیف فراهم نیست.']);
  $request->session()->put('coupon_code',$coupon->code);
  return back()->with('success','کد تخفیف با موفقیت اعمال شد.');
 }
 public function remove(Request $request): RedirectResponse {
  $request->session()->forget('coupon_code');
  return back()->with('success','کد تخفیف از سبد خرید حذف شد.');
 }
}