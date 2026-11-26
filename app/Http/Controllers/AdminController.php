<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;
class AdminController extends Controller {
 public function dashboard(): View { return view('admin.dashboard',['products'=>Product::count(),'categories'=>Category::count(),'orderCount'=>Order::count(),'orders'=>Order::latest()->take(8)->get()]); }
 public function products(): View { return view('admin.products.index',['products'=>Product::with('category')->latest()->paginate(20)]); }
 public function createProduct(): View { return view('admin.products.form',['product'=>new Product(),'categories'=>Category::orderBy('name')->get()]); }
 public function storeProduct(Request $request) {
  $data=$this->validatedProduct($request);if($request->hasFile('image'))$data['image']=$request->file('image')->store('products','public');
  if($request->hasFile('model_3d'))$data['model_3d']=$request->file('model_3d')->store('models','public');
  if(empty($data['slug']))$data['slug']='product-'.Str::lower(Str::random(10));
  Product::create($data);return redirect()->route('admin.products')->with('success','محصول ثبت شد.');
 }
 public function editProduct(Product $product): View { return view('admin.products.form',['product'=>$product,'categories'=>Category::orderBy('name')->get()]); }
 public function updateProduct(Request $request,Product $product) {
  $data=$this->validatedProduct($request,$product);if($request->hasFile('image')){if($product->image)Storage::disk('public')->delete($product->image);$data['image']=$request->file('image')->store('products','public');}
  if($request->hasFile('model_3d')){if($product->model_3d)Storage::disk('public')->delete($product->model_3d);$data['model_3d']=$request->file('model_3d')->store('models','public');}
  if(empty($data['slug']))$data['slug']=$product->slug;
  $product->update($data);return redirect()->route('admin.products')->with('success','محصول به‌روزرسانی شد.');
 }
 public function deleteProduct(Product $product) { if($product->image)Storage::disk('public')->delete($product->image);if($product->model_3d)Storage::disk('public')->delete($product->model_3d);$product->delete();return back()->with('success','محصول حذف شد.'); }
 public function categories(): View { return view('admin.categories.index',['categories'=>Category::orderBy('sort_order')->paginate(30)]); }
 public function createCategory(): View { return view('admin.categories.form',['category'=>new Category()]); }
 public function storeCategory(Request $request) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/','unique:categories,slug'],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0'],'is_active'=>['nullable','boolean']]);
  $data['is_active']=$request->boolean('is_active');Category::create($data);return redirect()->route('admin.categories')->with('success','دسته‌بندی ثبت شد.');
 }
 public function editCategory(Category $category): View { return view('admin.categories.form',compact('category')); }
 public function updateCategory(Request $request,Category $category) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/',Rule::unique('categories','slug')->ignore($category->id)],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0'],'is_active'=>['nullable','boolean']]);
  $data['is_active']=$request->boolean('is_active');$category->update($data);return redirect()->route('admin.categories')->with('success','دسته‌بندی ویرایش شد.');
 }
 public function deleteCategory(Category $category) { $category->delete();return back()->with('success','دسته‌بندی حذف شد.'); }
 public function orders(): View { return view('admin.orders.index',['orders'=>Order::latest()->paginate(25)]); }
 public function showOrder(Order $order): View { return view('admin.orders.show',['order'=>$order->load('items')]); }
 public function updateOrderStatus(Request $request,Order $order) {
  $data=$request->validate(['status'=>['required','in:pending,processing,shipped,delivered,cancelled']]);
  if($data['status']==='cancelled' && $order->payment_status==='paid')return back()->withErrors(['status'=>'سفارش پرداخت‌شده را نمی‌توان بدون ثبت بازپرداخت لغو کرد.']);
  if(in_array($data['status'],['processing','shipped','delivered'],true) && ($order->payment_status!=='paid'||!$order->stock_deducted_at))return back()->withErrors(['status'=>'تا پیش از تأیید پرداخت و کسر موجودی، امکان تغییر این وضعیت وجود ندارد.']);
  DB::transaction(function()use($order,$data){$locked=Order::whereKey($order->id)->lockForUpdate()->first();if($locked->payment_status==='paid'&&$data['status']==='cancelled')throw new \RuntimeException('سفارش پرداخت‌شده را نمی‌توان بدون ثبت بازپرداخت لغو کرد.');$locked->update(['status'=>$data['status']]);});
  return back()->with('success','وضعیت سفارش به‌روزرسانی شد.');
 }
 private function validatedProduct(Request $request,?Product $product=null): array {
  return $request->validate([
   'category_id'=>['nullable','exists:categories,id'],'name'=>['required','string','max:190'],
   'slug'=>['nullable','string','max:190','regex:/^[a-z0-9-]+$/',Rule::unique('products','slug')->ignore($product?->id)],
   'sku'=>['nullable','string','max:100',Rule::unique('products','sku')->ignore($product?->id)],
   'short_description'=>['nullable','string','max:500'],'description'=>['nullable','string'],
   'price'=>['required','integer','min:0'],'compare_price'=>['nullable','integer','min:0'],
   'stock'=>['required','integer','min:0'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'model_3d'=>['nullable','file','mimes:glb','max:20480'],
   'is_active'=>['nullable','boolean'],'is_featured'=>['nullable','boolean'],
   'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'canonical_url'=>['nullable','url','max:500']
  ])+['is_active'=>$request->boolean('is_active'),'is_featured'=>$request->boolean('is_featured')];
 }
}