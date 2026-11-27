<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
class ProductController extends Controller {
 public function index(Request $request): View {
  $filters=$request->validate([
   'q'=>['nullable','string','max:100'],
   'category'=>['nullable','string','max:150'],
   'min_price'=>['nullable','integer','min:0'],
   'max_price'=>['nullable','integer','min:0','gte:min_price'],
   'sort'=>['nullable','in:latest,price_asc,price_desc'],
   'in_stock'=>['nullable','boolean'],
  ]);
  $products=Product::with('category')->where('is_active',true)
   ->when(!empty($filters['category']),fn($q)=>$q->whereHas('category',fn($c)=>$c->where('slug',$filters['category'])))
   ->when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($search)use($term){$search->where('name','like',$term)->orWhere('short_description','like',$term)->orWhere('sku','like',$term);});})
   ->when(isset($filters['min_price']),fn($q)=>$q->where('price','>=',$filters['min_price']))
   ->when(isset($filters['max_price']),fn($q)=>$q->where('price','<=',$filters['max_price']))
   ->when(!empty($filters['in_stock']),fn($q)=>$q->whereRaw('products.stock > (SELECT COALESCE(SUM(stock_reservations.quantity),0) FROM stock_reservations WHERE stock_reservations.product_id = products.id AND stock_reservations.released_at IS NULL AND stock_reservations.consumed_at IS NULL AND stock_reservations.expires_at > ?)',[now()]))
   ->when(($filters['sort']??'latest')==='price_asc',fn($q)=>$q->orderBy('price'))
   ->when(($filters['sort']??'latest')==='price_desc',fn($q)=>$q->orderByDesc('price'))
   ->when(($filters['sort']??'latest')==='latest',fn($q)=>$q->latest())
   ->paginate(12)->withQueryString();
  return view('shop.index',['products'=>$products,'categories'=>Category::where('is_active',true)->orderBy('sort_order')->get(),'seoTitle'=>'فروشگاه محصولات زیبایی | '.StoreSetting::current()->store_name,'seoDescription'=>'محصولات مراقبت پوست، مو و زیبایی را بررسی کنید.']);
 }
 public function category(Category $category): View {
  abort_unless($category->is_active,404);
  $products=$category->products()->where('is_active',true)->latest()->paginate(12);
  return view('shop.category',['category'=>$category,'products'=>$products,'seoTitle'=>$category->seo_title ?: $category->name.' | '.config('app.name'),'seoDescription'=>$category->seo_description ?: ($category->description ?: 'مشاهده محصولات '.$category->name)]);
 }
 public function show(Product $product): View {
  abort_unless($product->is_active,404);
  $reserved=(int)DB::table('stock_reservations')->where('product_id',$product->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->sum('quantity');
  $availableStock=max(0,(int)$product->stock-$reserved);
  $images=[];
  if($product->image)$images[]=asset('storage/'.$product->image);
  foreach($product->gallery??[] as $image)$images[]=asset('storage/'.$image);
  $productSchema=['@context'=>'https://schema.org','@type'=>'Product','name'=>$product->name,'description'=>$product->short_description ?: $product->description,'sku'=>$product->sku,'category'=>$product->category?->name,'offers'=>['@type'=>'Offer','priceCurrency'=>'IRR','price'=>$product->price*10,'availability'=>$availableStock>0?'https://schema.org/InStock':'https://schema.org/OutOfStock','url'=>url()->current()]];
  if($images)$productSchema['image']=$images;
  $breadcrumbSchema=['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>[
   ['@type'=>'ListItem','position'=>1,'name'=>'خانه','item'=>route('home')],
   ['@type'=>'ListItem','position'=>2,'name'=>'فروشگاه','item'=>route('shop')],
   ['@type'=>'ListItem','position'=>3,'name'=>$product->name,'item'=>url()->current()],
  ]];
  $relatedProducts=Product::with('category')->where('is_active',true)->where('id','!=',$product->id)->when($product->category_id,fn($q)=>$q->where('category_id',$product->category_id))->latest()->take(4)->get();
  return view('shop.show',['product'=>$product,'availableStock'=>$availableStock,'relatedProducts'=>$relatedProducts,'productSchema'=>$productSchema,'breadcrumbSchema'=>$breadcrumbSchema,'seoTitle'=>$product->seo_title ?: $product->name.' | خرید آنلاین','seoDescription'=>$product->seo_description ?: ($product->short_description ?: 'مشاهده مشخصات محصول '.$product->name)]);
 }
}