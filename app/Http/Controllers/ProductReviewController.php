<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ProductReviewController extends Controller {
 public function store(Request $request,Product $product) {
  abort_unless(auth()->check()&&!auth()->user()->is_admin,403);
  $data=$request->validate(['rating'=>['required','integer','between:1,5'],'title'=>['nullable','string','max:120'],'body'=>['required','string','min:10','max:2000']]);
  $order=Order::where('user_id',auth()->id())->where('payment_status','paid')->whereHas('items',fn($q)=>$q->where('product_id',$product->id))->latest()->first();
  if(!$order)return back()->withErrors(['review'=>'ثبت نظر فقط برای خریداران تأییدشده این محصول امکان‌پذیر است.']);
  if(ProductReview::where('product_id',$product->id)->where('user_id',auth()->id())->exists())return back()->withErrors(['review'=>'شما قبلاً برای این محصول نظر ثبت کرده‌اید.']);
  ProductReview::create(['product_id'=>$product->id,'user_id'=>auth()->id(),'order_id'=>$order->id,'rating'=>$data['rating'],'title'=>$data['title']??null,'body'=>$data['body'],'status'=>'pending']);
  return back()->with('success','نظر شما ثبت شد و پس از بررسی نمایش داده می‌شود.');
 }
 public function moderate(Request $request,ProductReview $review) {
  $data=$request->validate(['status'=>['required','in:approved,rejected']]);
  $review->update(['status'=>$data['status']]);return back()->with('success','وضعیت نظر به‌روزرسانی شد.');
 }
 public function index(Request $request) {
  $filters=$request->validate(['status'=>['nullable','in:pending,approved,rejected'],'q'=>['nullable','string','max:100']]);
  $reviews=ProductReview::with(['product','user'])->when(!empty($filters['status']),fn($q)=>$q->where('status',$filters['status']))->when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(fn($s)=>$s->where('body','like',$term)->orWhere('title','like',$term)->orWhereHas('product',fn($p)=>$p->where('name','like',$term))->orWhereHas('user',fn($u)=>$u->where('name','like',$term)->orWhere('mobile','like',$term)));})->latest()->paginate(25)->withQueryString();
  return view('admin.reviews.index',compact('reviews','filters'));
 }
}