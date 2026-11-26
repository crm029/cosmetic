<?php
namespace App\Http\Controllers;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
class OrderTrackingController extends Controller {
 public function index(): View { return view('orders.track',['order'=>null]); }
 public function lookup(Request $request): View|RedirectResponse {
  $data=$request->validate(['order_number'=>['required','string','max:40'],'mobile'=>['required','regex:/^09[0-9]{9}$/']]);
  $order=Order::with('items')->where('order_number',strtoupper(trim($data['order_number'])))->where('mobile',$data['mobile'])->first();
  if(!$order)return back()->withErrors(['tracking'=>'سفارشی با این شماره و موبایل پیدا نشد.'])->withInput();
  return view('orders.track',compact('order'));
 }
}