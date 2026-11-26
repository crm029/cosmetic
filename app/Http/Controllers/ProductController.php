<?php
namespace App\\Http\\Controllers;
use App\\Models\\Category;
use App\\Models\\Product;
use Illuminate\\Http\\Request;
use Illuminate\\View\\View;
class ProductController extends Controller {
 public function index(Request $request): View {
  $products=Product::where('is_active',true)
   ->when($request->filled('category'),fn($q)=>$q->whereHas('category',fn($c)=>$c->where('slug',$request->string('category'))))
   ->when($request->filled('q'),fn($q)=>$q->where('name','like','%'.$request->string('q').'%'))
   ->latest()->paginate(12)->withQueryString();
  return view('shop.index',['products'=>$products,'categories'=>Category::where('is_active',true)->orderBy('sort_order')->get(),'seoTitle'=>'فروشگاه محصولات زیبایی | '.config('app.name'),'seoDescription'=>'محصولات مراقبت پوست، مو و زیبایی را بررسی کنید.']);
 }
 public function show(Product $product): View {
  abort_unless($product->is_active,404);
  return view('shop.show',['product'=>$product,'seoTitle'=>$product->seo_title ?: $product->name.' | خرید آنلاین','seoDescription'=>$product->seo_description ?: ($product->short_description ?: 'مشاهده مشخصات محصول '.$product->name)]);
 }
}