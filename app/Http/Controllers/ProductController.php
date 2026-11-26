<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;
class ProductController extends Controller {
 public function index(Request $request): View {
  $filters=$request->validate([
   'q'=>['nullable','string','max:100'],
   'category'=>['nullable','string','max:150'],
   'min_price'=>['nullable','integer','min:0'],
   'max_price'=>['nullable','integer','min:0'],
   'sort'=>['nullable','in:latest,price_asc,price_desc'],
   'in_stock'=>['nullable','boolean'],
  ]);
  $products=Product::with('category')->where('is_active',true)
   ->when(!empty($filters['category']),fn($q)=>$q->whereHas('category',fn($c)=>$c->where('slug',$filters['category'])))
   ->when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($search)use($term){$search->where('name','like',$term)->orWhere('short_description','like',$term)->orWhere('sku','like',$term);});})
   ->when(isset($filters['min_price']),fn($q)=>$q->where('price','>=',$filters['min_price']))
   ->when(isset($filters['max_price']),fn($q)=>$q->where('price','<=',$filters['max_price']))
   ->when(!empty($filters['in_stock']),fn($q)=>$q->where('stock','>',0))
   ->when(($filters['sort']??'latest')==='price_asc',fn($q)=>$q->orderBy('price'))
   ->when(($filters['sort']??'latest')==='price_desc',fn($q)=>$q->orderByDesc('price'))
   ->when(($filters['sort']??'latest')==='latest',fn($q)=>$q->latest())
   ->paginate(12)->withQueryString();
  return view('shop.index',['products'=>$products,'categories'=>Category::where('is_active',true)->orderBy('sort_order')->get(),'seoTitle'=>'فروشگاه محصولات زیبایی | '.config('app.name'),'seoDescription'=>'محصولات مراقبت پوست، مو و زیبایی را بررسی کنید.']);
 }
 public function category(Category $category): View {
  abort_unless($category->is_active,404);
  $products=$category->products()->where('is_active',true)->latest()->paginate(12);
  return view('shop.category',['category'=>$category,'products'=>$products,'seoTitle'=>$category->seo_title ?: $category->name.' | '.config('app.name'),'seoDescription'=>$category->seo_description ?: ($category->description ?: 'مشاهده محصولات '.$category->name)]);
 }
 public function show(Product $product): View {
  abort_unless($product->is_active,404);
  return view('shop.show',['product'=>$product,'seoTitle'=>$product->seo_title ?: $product->name.' | خرید آنلاین','seoDescription'=>$product->seo_description ?: ($product->short_description ?: 'مشاهده مشخصات محصول '.$product->name)]);
 }
}