<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StockMovement;
use App\Services\OrderFulfillmentService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\View\View;
class AdminController extends Controller {
 public function dashboard(): View { return view('admin.dashboard',['products'=>Product::count(),'categories'=>Category::count(),'orderCount'=>Order::count(),'unreadMessages'=>ContactMessage::where('is_read',false)->count(),'orders'=>Order::latest()->take(8)->get()]); }
 public function products(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:100'],'category_id'=>['nullable','integer','exists:categories,id'],'visibility'=>['nullable','in:active,inactive']]);
  $products=Product::with('category')->when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($s)use($term){$s->where('name','like',$term)->orWhere('sku','like',$term)->orWhere('slug','like',$term);});})->when(!empty($filters['category_id']),fn($q)=>$q->where('category_id',$filters['category_id']))->when(($filters['visibility']??null)==='active',fn($q)=>$q->where('is_active',true))->when(($filters['visibility']??null)==='inactive',fn($q)=>$q->where('is_active',false))->latest()->paginate(20)->withQueryString();
  return view('admin.products.index',['products'=>$products,'categories'=>Category::orderBy('name')->get()]);
 }
 public function createProduct(): View { return view('admin.products.form',['product'=>new Product(),'categories'=>Category::orderBy('name')->get()]); }
 public function storeProduct(Request $request) {
  $data=$this->validatedProduct($request);$galleryFiles=$request->file('gallery_images',[]);unset($data['gallery_images'],$data['remove_gallery']);if($galleryFiles)$data['gallery']=array_map(fn($file)=>$file->store('products/gallery','public'),$galleryFiles);if($request->hasFile('image'))$data['image']=$request->file('image')->store('products','public');
  if($request->hasFile('model_3d'))$data['model_3d']=$request->file('model_3d')->store('models','public');
  if(empty($data['slug']))$data['slug']='product-'.Str::lower(Str::random(10));
  $product=Product::create($data);if($product->stock>0)StockMovement::create(['product_id'=>$product->id,'admin_user_id'=>auth()->id(),'type'=>'initial','quantity'=>$product->stock,'previous_stock'=>0,'new_stock'=>$product->stock,'note'=>'موجودی اولیه هنگام ثبت محصول']);return redirect()->route('admin.products')->with('success','محصول ثبت شد.');
 }
 public function editProduct(Product $product): View { return view('admin.products.form',['product'=>$product,'categories'=>Category::orderBy('name')->get()]); }
 public function updateProduct(Request $request,Product $product) {
  $data=$this->validatedProduct($request,$product);$galleryFiles=$request->file('gallery_images',[]);unset($data['gallery_images'],$data['remove_gallery']);$gallery=$product->gallery??[];foreach((array)$request->input('remove_gallery',[]) as $index){if(isset($gallery[$index])){Storage::disk('public')->delete($gallery[$index]);unset($gallery[$index]);}}$gallery=array_values($gallery);if(count($gallery)+count($galleryFiles)>8)return back()->withErrors(['gallery_images'=>'هر محصول حداکثر ۸ تصویر گالری می‌تواند داشته باشد.'])->withInput();foreach($galleryFiles as $file)$gallery[]=$file->store('products/gallery','public');if($gallery!==($product->gallery??[]))$data['gallery']=$gallery;if($request->hasFile('image')){if($product->image)Storage::disk('public')->delete($product->image);$data['image']=$request->file('image')->store('products','public');}
  if($request->hasFile('model_3d')){if($product->model_3d)Storage::disk('public')->delete($product->model_3d);$data['model_3d']=$request->file('model_3d')->store('models','public');}
  if(empty($data['slug']))$data['slug']=$product->slug;
  $previousStock=$product->stock;$product->update($data);$newStock=(int)$product->fresh()->stock;if($newStock!==$previousStock)StockMovement::create(['product_id'=>$product->id,'admin_user_id'=>auth()->id(),'type'=>'adjustment','quantity'=>$newStock-$previousStock,'previous_stock'=>$previousStock,'new_stock'=>$newStock,'note'=>'اصلاح موجودی از پنل مدیریت']);return redirect()->route('admin.products')->with('success','محصول به‌روزرسانی شد.');
 }
 public function stockHistory(Product $product): View { return view('admin.products.stock-history',['product'=>$product,'movements'=>StockMovement::with(['admin','order'])->where('product_id',$product->id)->latest()->paginate(30)]); }
 public function deleteProduct(Product $product) { if($product->image)Storage::disk('public')->delete($product->image);foreach($product->gallery??[] as $image)Storage::disk('public')->delete($image);if($product->model_3d)Storage::disk('public')->delete($product->model_3d);$product->delete();return back()->with('success','محصول حذف شد.'); }
 public function categories(): View { return view('admin.categories.index',['categories'=>Category::orderBy('sort_order')->paginate(30)]); }
 public function createCategory(): View { return view('admin.categories.form',['category'=>new Category()]); }
 public function storeCategory(Request $request) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/','unique:categories,slug'],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'is_active'=>['nullable','boolean']]);
  if($request->hasFile('image'))$data['image']=$request->file('image')->store('categories','public');
  $data['is_active']=$request->boolean('is_active');Category::create($data);return redirect()->route('admin.categories')->with('success','دسته‌بندی ثبت شد.');
 }
 public function editCategory(Category $category): View { return view('admin.categories.form',compact('category')); }
 public function updateCategory(Request $request,Category $category) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/',Rule::unique('categories','slug')->ignore($category->id)],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'is_active'=>['nullable','boolean']]);
  if($request->hasFile('image')){if($category->image)Storage::disk('public')->delete($category->image);$data['image']=$request->file('image')->store('categories','public');}
  $data['is_active']=$request->boolean('is_active');$category->update($data);return redirect()->route('admin.categories')->with('success','دسته‌بندی ویرایش شد.');
 }
 public function deleteCategory(Category $category) { if($category->image)Storage::disk('public')->delete($category->image);$category->delete();return back()->with('success','دسته‌بندی حذف شد.'); }
 public function settings(): View { return view('admin.settings', ['settings'=>StoreSetting::current()]); }
 public function updateSettings(Request $request) {
  $data=$request->validate(['store_name'=>['required','string','max:150'],'tagline'=>['nullable','string','max:255'],'shipping_fee_toman'=>['required','integer','min:0','max:100000000'],'free_shipping_threshold_toman'=>['required','integer','min:0','max:100000000'],'support_mobile'=>['nullable','regex:/^09[0-9]{9}$/'],'support_email'=>['nullable','email','max:190'],'address'=>['nullable','string','max:1500'],'ink_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/']]);
  StoreSetting::current()->update($data);return back()->with('success','تنظیمات فروشگاه ذخیره شد.');
 }
 public function messages(): View { return view('admin.messages.index',['messages'=>ContactMessage::latest()->paginate(25)]); }
 public function markMessageRead(ContactMessage $message) { $message->update(['is_read'=>true]);return back()->with('success','پیام خوانده‌شده علامت‌گذاری شد.'); }
 public function orders(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:100'],'status'=>['nullable','in:pending,processing,shipped,delivered,cancelled,paid_stock_issue'],'payment_status'=>['nullable','in:unpaid,paid,failed,refunded']]);
  $orders=Order::when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($s)use($term){$s->where('order_number','like',$term)->orWhere('customer_name','like',$term)->orWhere('mobile','like',$term);});})->when(!empty($filters['status']),fn($q)=>$q->where('status',$filters['status']))->when(!empty($filters['payment_status']),fn($q)=>$q->where('payment_status',$filters['payment_status']))->latest()->paginate(25)->withQueryString();
  return view('admin.orders.index',compact('orders'));
 }
 public function showOrder(Order $order): View { return view('admin.orders.show',['order'=>$order->load('items')]); }
 public function reconcileOrder(Order $order,OrderFulfillmentService $fulfillment,SmsService $sms) {
  if($order->payment_status!=='paid')return back()->withErrors(['stock'=>'فقط سفارش پرداخت‌شده قابل بررسی مجدد است.']);
  if(!$fulfillment->deductStock($order))return back()->withErrors(['stock'=>'موجودی کافی نیست یا محصول حذف شده است؛ پس از اصلاح موجودی دوباره تلاش کنید.']);
  $fresh=$order->fresh();if(!$fresh->sms_sent_at&&$sms->sendOrderPaid($fresh))$fresh->update(['sms_sent_at'=>now()]);
  return back()->with('success','موجودی سفارش با موفقیت کسر و سفارش به مرحله آماده‌سازی منتقل شد.');
 }
 public function updateOrderStatus(Request $request,Order $order) {
  $data=$request->validate(['status'=>['required','in:pending,processing,shipped,delivered,cancelled'],'shipping_carrier'=>['nullable','string','max:100'],'tracking_code'=>['nullable','string','max:120']]);
  $error=DB::transaction(function()use($order,$data){
   $locked=Order::whereKey($order->id)->lockForUpdate()->first();
   if($locked->status==='cancelled'&&$data['status']!=='cancelled')return 'سفارش لغوشده قابل فعال‌سازی مجدد نیست.';
   if($locked->status==='paid_stock_issue'&&!$locked->stock_deducted_at)return 'ابتدا مشکل موجودی را از طریق گزینه تلاش مجدد برای کسر موجودی برطرف کنید.';
   if($locked->payment_status==='paid'&&$data['status']==='cancelled')return 'سفارش پرداخت‌شده را نمی‌توان بدون ثبت بازپرداخت لغو کرد.';
   if(in_array($data['status'],['processing','shipped','delivered'],true)&&($locked->payment_status!=='paid'||!$locked->stock_deducted_at))return 'تا پیش از تأیید پرداخت و کسر موجودی، امکان تغییر این وضعیت وجود ندارد.';
   $rank=['pending'=>0,'processing'=>1,'shipped'=>2,'delivered'=>3];
   if(isset($rank[$locked->status],$rank[$data['status']])&&$rank[$data['status']]<$rank[$locked->status])return 'وضعیت سفارش امکان بازگشت به مرحله قبلی را ندارد.';
   $changes=['status'=>$data['status']];if($data['status']==='shipped')$changes['shipped_at']=$locked->shipped_at??now();if($data['status']==='delivered')$changes['delivered_at']=$locked->delivered_at??now();if(!empty($data['shipping_carrier']))$changes['shipping_carrier']=$data['shipping_carrier'];if(!empty($data['tracking_code']))$changes['tracking_code']=$data['tracking_code'];$locked->update($changes);return null;
  });
  if($error)return back()->withErrors(['status'=>$error]);
  return back()->with('success','وضعیت سفارش به‌روزرسانی شد.');
 }
 private function validatedProduct(Request $request,?Product $product=null): array {
  return $request->validate([
   'category_id'=>['nullable','exists:categories,id'],'name'=>['required','string','max:190'],
   'slug'=>['nullable','string','max:190','regex:/^[a-z0-9-]+$/',Rule::unique('products','slug')->ignore($product?->id)],
   'sku'=>['nullable','string','max:100',Rule::unique('products','sku')->ignore($product?->id)],
   'short_description'=>['nullable','string','max:500'],'description'=>['nullable','string'],
   'price'=>['required','integer','min:0'],'compare_price'=>['nullable','integer','min:0','gt:price'],
   'stock'=>['required','integer','min:0'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'gallery_images'=>['nullable','array','max:8'],'gallery_images.*'=>['image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'remove_gallery'=>['nullable','array'],'remove_gallery.*'=>['integer','min:0'],'model_3d'=>['nullable','file','mimes:glb','max:20480'],
   'is_active'=>['nullable','boolean'],'is_featured'=>['nullable','boolean'],
   'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'canonical_url'=>['nullable','url','max:500']
  ])+['is_active'=>$request->boolean('is_active'),'is_featured'=>$request->boolean('is_featured')];
 }
}