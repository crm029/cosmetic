<?php
namespace App\Http\Controllers;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class CartController extends Controller {
 public function index(Request $request): View {
  $cart=$request->session()->get('cart',[]);$ids=array_keys($cart);
  $products=Product::whereIn('id',$ids)->get()->keyBy('id');
  $lines=[];$total=0;
  foreach($cart as $id=>$quantity){$p=$products->get($id);if(!$p||!$p->is_active)continue;$line=$p->price*(int)$quantity;$total+=$line;$lines[]=['product'=>$p,'quantity'=>(int)$quantity,'line_total'=>$line];}
  return view('cart.index',compact('lines','total'));
 }
 public function add(Request $request, Product $product): RedirectResponse {
  abort_unless($product->is_active,404);
  $data=$request->validate(['quantity'=>['nullable','integer','min:1','max:20']]);
  $quantity=(int)($data['quantity']??1);$cart=$request->session()->get('cart',[]);
  $new=(int)($cart[$product->id]??0)+$quantity;
  if($new>$product->stock)return back()->withErrors(['cart'=>'تعداد انتخاب‌شده بیشتر از موجودی محصول است.']);
  $cart[$product->id]=$new;$request->session()->put('cart',$cart);
  return redirect()->route('cart.index')->with('success','محصول به سبد خرید اضافه شد.');
 }
 public function update(Request $request, Product $product): RedirectResponse {
  $data=$request->validate(['quantity'=>['required','integer','min:1','max:20']]);
  if(!$product->is_active){$cart=$request->session()->get('cart',[]);unset($cart[$product->id]);$request->session()->put('cart',$cart);return back()->withErrors(['cart'=>'این محصول دیگر برای فروش فعال نیست و از سبد خرید حذف شد.']);}
  if($data['quantity']>$product->stock)return back()->withErrors(['cart'=>'موجودی کافی نیست.']);
  $cart=$request->session()->get('cart',[]);$cart[$product->id]=$data['quantity'];$request->session()->put('cart',$cart);
  return back()->with('success','سبد خرید به‌روزرسانی شد.');
 }
 public function remove(Request $request, Product $product): RedirectResponse {
  $cart=$request->session()->get('cart',[]);unset($cart[$product->id]);$request->session()->put('cart',$cart);
  return back()->with('success','محصول از سبد حذف شد.');
 }
}