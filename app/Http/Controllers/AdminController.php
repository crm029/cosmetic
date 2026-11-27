<?php
namespace App\Http\Controllers;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\StockMovement;
use App\Services\OrderFulfillmentService;
use App\Services\StockReservationService;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Illuminate\View\View;
class AdminController extends Controller {
 public function dashboard(): View { return view('admin.dashboard',['products'=>Product::count(),'categories'=>Category::count(),'customerCount'=>Order::distinct('mobile')->count('mobile'),'orderCount'=>Order::count(),'paidRevenue'=>Order::where('payment_status','paid')->sum('total'),'pendingCount'=>Order::where('payment_status','unpaid')->count(),'lowStockCount'=>Product::where('is_active',true)->whereRaw('(products.stock - COALESCE((SELECT SUM(quantity) FROM stock_reservations WHERE stock_reservations.product_id = products.id AND stock_reservations.released_at IS NULL AND stock_reservations.consumed_at IS NULL AND stock_reservations.expires_at > ?), 0) BETWEEN 1 AND 5)',[now()])->count(),'unreadMessages'=>ContactMessage::where('is_read',false)->count(),'orders'=>Order::latest()->take(8)->get()]); }
 public function products(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:100'],'category_id'=>['nullable','integer','exists:categories,id'],'visibility'=>['nullable','in:active,inactive'],'stock_status'=>['nullable','in:low,out']]);
  $reservedQuery=DB::table('stock_reservations')->selectRaw('COALESCE(SUM(quantity), 0)')->whereColumn('product_id','products.id')->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now());
  $availableExpression="products.stock - (SELECT COALESCE(SUM(quantity), 0) FROM stock_reservations WHERE stock_reservations.product_id = products.id AND stock_reservations.released_at IS NULL AND stock_reservations.consumed_at IS NULL AND stock_reservations.expires_at > ?)";
  $products=Product::with('category')->select('products.*')->selectSub($reservedQuery,'reserved_stock')
   ->when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($s)use($term){$s->where('name','like',$term)->orWhere('sku','like',$term)->orWhere('slug','like',$term);});})
   ->when(!empty($filters['category_id']),fn($q)=>$q->where('category_id',$filters['category_id']))
   ->when(($filters['visibility']??null)==='active',fn($q)=>$q->where('is_active',true))
   ->when(($filters['visibility']??null)==='inactive',fn($q)=>$q->where('is_active',false))
   ->when(($filters['stock_status']??null)==='low',function($q)use($availableExpression){$q->whereRaw("($availableExpression) BETWEEN 1 AND 5",[now()]);})
   ->when(($filters['stock_status']??null)==='out',function($q)use($availableExpression){$q->whereRaw("($availableExpression) <= 0",[now()]);})
   ->latest()->paginate(20)->withQueryString();
  return view('admin.products.index',['products'=>$products,'categories'=>Category::orderBy('name')->get()]);
 }
 public function createProduct(): View { return view('admin.products.form',['product'=>new Product(),'categories'=>Category::orderBy('name')->get()]); }
 public function storeProduct(Request $request) {
  $data=$this->validatedProduct($request);
  $galleryFiles=$request->file('gallery_images',[]);
  unset($data['gallery_images'],$data['remove_gallery'],$data['remove_image'],$data['remove_model_3d']);
  $newFiles=[];
  try {
   if($galleryFiles){$data['gallery']=[];foreach($galleryFiles as $file){$stored=$file->store('products/gallery','public');$data['gallery'][]=$stored;$newFiles[]=$stored;}}
   if($request->hasFile('image')){$data['image']=$request->file('image')->store('products','public');$newFiles[]=$data['image'];}
   if($request->hasFile('model_3d')){$data['model_3d']=$request->file('model_3d')->store('models','public');$newFiles[]=$data['model_3d'];}
   if(empty($data['slug']))$data['slug']='product-'.Str::lower(Str::random(10));
   DB::transaction(function()use($data){
    $product=Product::create($data);
    if($product->stock>0)StockMovement::create(['product_id'=>$product->id,'admin_user_id'=>auth()->id(),'type'=>'initial','quantity'=>$product->stock,'previous_stock'=>0,'new_stock'=>$product->stock,'note'=>'موجودی اولیه هنگام ثبت محصول']);
   });
  } catch(\Throwable $e) {
   foreach($newFiles as $file)Storage::disk('public')->delete($file);
   throw $e;
  }
  return redirect()->route('admin.products')->with('success','محصول ثبت شد.');
 }
 public function editProduct(Product $product): View { return view('admin.products.form',['product'=>$product,'categories'=>Category::orderBy('name')->get()]); }
 public function updateProduct(Request $request,Product $product) {
  $data=$this->validatedProduct($request,$product);
  $galleryFiles=$request->file('gallery_images',[]);
  unset($data['gallery_images'],$data['remove_gallery'],$data['remove_image'],$data['remove_model_3d']);
  $gallery=$product->gallery??[];
  $removedGallery=[];
  foreach((array)$request->input('remove_gallery',[]) as $index){
   if(isset($gallery[$index])){$removedGallery[]=$gallery[$index];unset($gallery[$index]);}
  }
  $gallery=array_values($gallery);
  if(count($gallery)+count($galleryFiles)>8)return back()->withErrors(['gallery_images'=>'هر محصول حداکثر ۸ تصویر گالری می‌تواند داشته باشد.'])->withInput();
  $newFiles=[];
  $obsoleteFiles=$removedGallery;
  try {
   foreach($galleryFiles as $file){$stored=$file->store('products/gallery','public');$gallery[]=$stored;$newFiles[]=$stored;}
   if($gallery!==($product->gallery??[]))$data['gallery']=$gallery;
   if($request->hasFile('image')){
    $data['image']=$request->file('image')->store('products','public');$newFiles[]=$data['image'];
    if($product->image)$obsoleteFiles[]=$product->image;
   } elseif($request->boolean('remove_image')){
    $data['image']=null;if($product->image)$obsoleteFiles[]=$product->image;
   }
   if($request->hasFile('model_3d')){
    $data['model_3d']=$request->file('model_3d')->store('models','public');$newFiles[]=$data['model_3d'];
    if($product->model_3d)$obsoleteFiles[]=$product->model_3d;
   } elseif($request->boolean('remove_model_3d')){
    $data['model_3d']=null;if($product->model_3d)$obsoleteFiles[]=$product->model_3d;
   }
   if(empty($data['slug']))$data['slug']=$product->slug;
   $requestedStock=(int)$data['stock'];unset($data['stock']);
   DB::transaction(function()use($product,$data,$requestedStock){
    $locked=Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
    $previousStock=(int)$locked->stock;
    $reserved=(int)DB::table('stock_reservations')->where('product_id',$locked->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->sum('quantity');
    if($requestedStock<$reserved)throw ValidationException::withMessages(['stock'=>'موجودی جدید نمی‌تواند از تعداد رزروشده برای سفارش‌های در انتظار کمتر باشد.']);
    $locked->update($data);
    if($requestedStock!==$previousStock){
     $locked->stock=$requestedStock;$locked->save();
     StockMovement::create(['product_id'=>$locked->id,'admin_user_id'=>auth()->id(),'type'=>'adjustment','quantity'=>$requestedStock-$previousStock,'previous_stock'=>$previousStock,'new_stock'=>$requestedStock,'note'=>'اصلاح موجودی از پنل مدیریت']);
    }
   });
  } catch(\Throwable $e) {
   foreach($newFiles as $file)Storage::disk('public')->delete($file);
   throw $e;
  }
  foreach(array_unique($obsoleteFiles) as $file)Storage::disk('public')->delete($file);
  return redirect()->route('admin.products')->with('success','محصول به‌روزرسانی شد.');
 }
 public function stockHistory(Product $product): View { return view('admin.products.stock-history',['product'=>$product,'movements'=>StockMovement::with(['admin','order'])->where('product_id',$product->id)->latest()->paginate(30)]); }
 public function deleteProduct(Product $product) { $deleted=DB::transaction(function()use($product){$locked=Product::whereKey($product->id)->lockForUpdate()->firstOrFail();$reserved=(int)DB::table('stock_reservations')->where('product_id',$locked->id)->whereNull('released_at')->whereNull('consumed_at')->where('expires_at','>',now())->sum('quantity');if($reserved>0)return false;$locked->delete();return true;});if(!$deleted)return back()->withErrors(['product'=>'این محصول در سفارش‌های در انتظار پرداخت رزرو شده و فعلاً قابل حذف نیست.']);if($product->image)Storage::disk('public')->delete($product->image);foreach($product->gallery??[] as $image)Storage::disk('public')->delete($image);if($product->model_3d)Storage::disk('public')->delete($product->model_3d);return back()->with('success','محصول حذف شد.'); }
 public function categories(): View { return view('admin.categories.index',['categories'=>Category::orderBy('sort_order')->paginate(30)]); }
 public function createCategory(): View { return view('admin.categories.form',['category'=>new Category()]); }
 public function storeCategory(Request $request) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/','unique:categories,slug'],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0','max:4294967295'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'is_active'=>['nullable','boolean']]);
  unset($data['remove_image']);
  $imagePath=null;
  try {
   if($request->hasFile('image')){$imagePath=$request->file('image')->store('categories','public');$data['image']=$imagePath;}
   $data['is_active']=$request->boolean('is_active');
   Category::create($data);
  } catch(\Throwable $e) {
   if($imagePath)Storage::disk('public')->delete($imagePath);
   throw $e;
  }
  return redirect()->route('admin.categories')->with('success','دسته‌بندی ثبت شد.');
 }
 public function editCategory(Category $category): View { return view('admin.categories.form',compact('category')); }
 public function updateCategory(Request $request,Category $category) {
  $data=$request->validate(['name'=>['required','string','max:150'],'slug'=>['required','string','max:150','regex:/^[a-z0-9-]+$/',Rule::unique('categories','slug')->ignore($category->id)],'description'=>['nullable','string'],'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'sort_order'=>['nullable','integer','min:0','max:4294967295'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'is_active'=>['nullable','boolean']]);
  $oldImage=$category->image;
  $removeImage=$request->boolean('remove_image')&&!$request->hasFile('image');
  unset($data['remove_image']);
  if($removeImage)$data['image']=null;
  $newImage=null;
  try {
   if($request->hasFile('image')){$newImage=$request->file('image')->store('categories','public');$data['image']=$newImage;}
   $data['is_active']=$request->boolean('is_active');
   $category->update($data);
  } catch(\Throwable $e) {
   if($newImage)Storage::disk('public')->delete($newImage);
   throw $e;
  }
  if(($newImage||$removeImage)&&$oldImage&&$oldImage!==$newImage)Storage::disk('public')->delete($oldImage);
  return redirect()->route('admin.categories')->with('success','دسته‌بندی ویرایش شد.');
 }
 public function deleteCategory(Category $category) {
  $image=$category->image;
  DB::transaction(function()use($category){Category::whereKey($category->id)->lockForUpdate()->firstOrFail()->delete();});
  if($image)Storage::disk('public')->delete($image);
  return back()->with('success','دسته‌بندی حذف شد.');
 }
 public function settings(): View { return view('admin.settings', ['settings'=>StoreSetting::current()]); }
 public function updateSettings(Request $request) {
  $data=$request->validate(['store_name'=>['required','string','max:150'],'tagline'=>['nullable','string','max:255'],'shipping_fee_toman'=>['required','integer','min:0','max:100000000'],'free_shipping_threshold_toman'=>['required','integer','min:0','max:100000000'],'support_mobile'=>['nullable','regex:/^09[0-9]{9}$/'],'support_email'=>['nullable','email','max:190'],'address'=>['nullable','string','max:1500'],'ink_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'accent_color'=>['required','regex:/^#[0-9A-Fa-f]{6}$/'],'logo'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:2048'],'remove_logo'=>['nullable','boolean']]);
  $settings=StoreSetting::current();$oldLogo=$settings->logo_path;$newLogo=null;
  $removeLogo=$request->boolean('remove_logo')&&!$request->hasFile('logo');
  unset($data['logo'],$data['remove_logo']);
  try {
   if($request->hasFile('logo')){$newLogo=$request->file('logo')->store('store','public');$data['logo_path']=$newLogo;}
   elseif($removeLogo)$data['logo_path']=null;
   $settings->update($data);
  } catch(\Throwable $e) {
   if($newLogo)Storage::disk('public')->delete($newLogo);
   throw $e;
  }
  if(($newLogo||$removeLogo)&&$oldLogo&&$oldLogo!==$newLogo)Storage::disk('public')->delete($oldLogo);
  return back()->with('success','تنظیمات فروشگاه ذخیره شد.');
 }
 public function messages(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:120'],'status'=>['nullable','in:all,unread,read']]);
  $messages=ContactMessage::query()
   ->when(!empty($filters['q']),function($query)use($filters){
    $term='%'.$filters['q'].'%';
    $query->where(function($search)use($term){
     $search->where('name','like',$term)->orWhere('mobile','like',$term)
      ->orWhere('email','like',$term)->orWhere('subject','like',$term)->orWhere('message','like',$term);
    });
   })
   ->when(($filters['status']??'all')==='unread',fn($query)=>$query->where('is_read',false))
   ->when(($filters['status']??'all')==='read',fn($query)=>$query->where('is_read',true))
   ->latest()->paginate(25)->withQueryString();
  return view('admin.messages.index',[
   'messages'=>$messages,
   'unreadCount'=>ContactMessage::where('is_read',false)->count(),
   'totalCount'=>ContactMessage::count(),
   'filters'=>$filters,
  ]);
 }
 public function markMessageRead(Request $request,ContactMessage $message) {
  $data=$request->validate(['is_read'=>['sometimes','boolean']]);
  $isRead=(bool)($data['is_read']??true);
  $message->update(['is_read'=>$isRead]);
  return back()->with('success',$isRead?'پیام به‌عنوان خوانده‌شده ثبت شد.':'پیام به فهرست خوانده‌نشده‌ها برگشت.');
 }
 public function orders(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:100'],'status'=>['nullable','in:pending,processing,shipped,delivered,cancelled,paid_stock_issue,refund_required'],'payment_status'=>['nullable','in:unpaid,paid,failed,refunded']]);
  $orders=Order::when(!empty($filters['q']),function($q)use($filters){$term='%'.$filters['q'].'%';$q->where(function($s)use($term){$s->where('order_number','like',$term)->orWhere('customer_name','like',$term)->orWhere('mobile','like',$term);});})->when(!empty($filters['status']),fn($q)=>$q->where('status',$filters['status']))->when(!empty($filters['payment_status']),fn($q)=>$q->where('payment_status',$filters['payment_status']))->latest()->paginate(25)->withQueryString();
  return view('admin.orders.index',compact('orders'));
 }
 public function reports(Request $request): View {
  $filters=$request->validate(['from'=>['nullable','date'],'to'=>['nullable','date','after_or_equal:from']]);
  $end=!empty($filters['to'])?now()->parse($filters['to'])->endOfDay():now()->endOfDay();
  $start=!empty($filters['from'])?now()->parse($filters['from'])->startOfDay():$end->copy()->subDays(29)->startOfDay();
  if($start->greaterThan($end))throw ValidationException::withMessages(['from'=>'تاریخ شروع نمی‌تواند بعد از تاریخ پایان باشد.']);
  if($start->diffInDays($end)>365)throw ValidationException::withMessages(['to'=>'بازه گزارش نمی‌تواند بیشتر از ۳۶۵ روز باشد.']);
  $range=[$start,$end];
  $orders=Order::whereBetween('created_at',$range);
  $paidOrders=(clone $orders)->where('payment_status','paid');
  $paidRevenue=(int)(clone $paidOrders)->sum('total');
  $paidOrderCount=(clone $paidOrders)->count();
  $dailyRows=DB::table('orders')->selectRaw('DATE(created_at) as day, SUM(total) as revenue, COUNT(*) as order_count')
   ->whereBetween('created_at',$range)->where('payment_status','paid')->groupBy('day')->orderBy('day')->get()->keyBy('day');
  $dailySales=collect();
  for($day=$start->copy()->startOfDay();$day<=$end->copy()->startOfDay();$day->addDay()){
   $key=$day->toDateString();$row=$dailyRows->get($key);
   $dailySales->push(['date'=>$key,'label'=>$day->format('m/d'),'revenue'=>(int)($row->revenue??0),'orders'=>(int)($row->order_count??0)]);
  }
  $topProducts=DB::table('order_items')->join('orders','orders.id','=','order_items.order_id')
   ->selectRaw('order_items.product_name, SUM(order_items.quantity) as quantity, SUM(order_items.line_total) as revenue')
   ->whereBetween('orders.created_at',$range)->where('orders.payment_status','paid')
   ->groupBy('order_items.product_name')->orderByDesc('quantity')->limit(10)->get();
  $topCustomers=(clone $paidOrders)->selectRaw('mobile, MAX(customer_name) as customer_name, COUNT(*) as orders_count, SUM(total) as paid_total')
   ->groupBy('mobile')->orderByDesc('paid_total')->limit(5)->get();
  return view('admin.reports.index',[
   'startDate'=>$start->toDateString(),'endDate'=>$end->toDateString(),
   'orderCount'=>(clone $orders)->count(),'paidOrderCount'=>$paidOrderCount,
   'unpaidOrderCount'=>(clone $orders)->where('payment_status','unpaid')->count(),
   'refundedTotal'=>(int)(clone $orders)->where('payment_status','refunded')->sum('total'),
   'paidRevenue'=>$paidRevenue,'averageOrderValue'=>$paidOrderCount?(int)round($paidRevenue/$paidOrderCount):0,
   'dailySales'=>$dailySales,'maxDailyRevenue'=>max(1,(int)$dailySales->max('revenue')),
   'topProducts'=>$topProducts,'topCustomers'=>$topCustomers,
  ]);
 }
 public function customers(Request $request): View {
  $filters=$request->validate(['q'=>['nullable','string','max:100']]);
  $customers=Order::query()
   ->selectRaw("orders.mobile, (SELECT latest.customer_name FROM orders as latest WHERE latest.mobile = orders.mobile ORDER BY latest.created_at DESC, latest.id DESC LIMIT 1) as customer_name, (SELECT latest.email FROM orders as latest WHERE latest.mobile = orders.mobile ORDER BY latest.created_at DESC, latest.id DESC LIMIT 1) as email, COUNT(*) as orders_count, SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as paid_total, MAX(created_at) as last_order_at")
   ->when(!empty($filters['q']),function($query)use($filters){$term='%'.$filters['q'].'%';$query->where(function($search)use($term){$search->where('customer_name','like',$term)->orWhere('mobile','like',$term)->orWhere('email','like',$term);});})
   ->groupBy('mobile')->orderByDesc('last_order_at')->paginate(25)->withQueryString();
  return view('admin.customers.index',compact('customers'));
 }
 public function showCustomer(string $mobile): View {
  abort_unless(preg_match('/^[0-9+ -]{7,20}$/',$mobile)===1,404);
  $query=Order::where('mobile',$mobile);
  $latest=(clone $query)->latest()->firstOrFail();
  return view('admin.customers.show',[
   'mobile'=>$mobile,
   'customerName'=>$latest->customer_name,
   'email'=>$latest->email,
   'orderCount'=>(clone $query)->count(),
   'paidTotal'=>(clone $query)->where('payment_status','paid')->sum('total'),
   'orders'=>(clone $query)->latest()->paginate(20),
  ]);
 }
 public function showOrder(Order $order): View { return view('admin.orders.show',['order'=>$order->load(['items','statusHistory'])]); }
 public function reconcileOrder(Order $order,OrderFulfillmentService $fulfillment,StockReservationService $reservations,SmsService $sms) {
  if($order->payment_status!=='paid'||in_array($order->status,['refund_required','cancelled'],true))return back()->withErrors(['stock'=>'این سفارش برای بازپرداخت علامت‌گذاری شده یا قابل آماده‌سازی نیست.']);
  try {
   $deducted=DB::transaction(function()use($order,$fulfillment,$reservations){
    $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
    if($locked->stock_deducted_at)return true;
    if($locked->payment_status!=='paid'||in_array($locked->status,['refund_required','cancelled'],true))return false;
    $reservations->reserveForOrder($locked);
    return $fulfillment->deductStock($locked);
   });
  } catch(\RuntimeException $e) {
   DB::transaction(function()use($order,$reservations,$e){
    $locked=Order::whereKey($order->id)->lockForUpdate()->first();
    if($locked&&$locked->payment_status==='paid'&&!$locked->stock_deducted_at){
     $reservations->releaseForOrder($locked);
     $previousStatus=$locked->status;
     $locked->update(['status'=>'paid_stock_issue']);
     if($previousStatus!=='paid_stock_issue'){
      OrderStatusHistory::create([
       'order_id'=>$locked->id,'from_status'=>$previousStatus,'to_status'=>'paid_stock_issue',
       'actor_type'=>'system','note'=>'تلاش برای تطبیق موجودی ناموفق بود: '.$e->getMessage(),
      ]);
     }
    }
   });
   return back()->withErrors(['stock'=>$e->getMessage()]);
  }
  if(!$deducted)return back()->withErrors(['stock'=>'موجودی کافی نیست یا محصول حذف شده است؛ پس از اصلاح موجودی دوباره تلاش کنید.']);
  $sms->sendOrderPaidOnce($order->fresh());
  return back()->with('success','رزرو تازه ثبت شد، موجودی سفارش کسر و سفارش به مرحله آماده‌سازی منتقل شد.');
 }
 public function recordRefund(Request $request,Order $order) {
  $data=$request->validate(['refund_ref_id'=>['required','string','max:120'],'refund_note'=>['required','string','min:5','max:1000']]);
  $error=DB::transaction(function()use($order,$data){
   $locked=Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
   if($locked->payment_status==='refunded')return 'بازپرداخت این سفارش قبلاً ثبت شده است.';
   if($locked->payment_status!=='paid'||$locked->status!=='refund_required')return 'فقط سفارش‌های پرداخت‌شده و نیازمند بازپرداخت قابل ثبت هستند.';
   $locked->update(['payment_status'=>'refunded','refund_ref_id'=>$data['refund_ref_id'],'refund_note'=>$data['refund_note'],'refunded_at'=>now(),'status'=>'cancelled']);
   OrderStatusHistory::create([
    'order_id'=>$locked->id,'admin_user_id'=>auth()->id(),
    'from_status'=>'refund_required','to_status'=>'cancelled','actor_type'=>'admin',
    'note'=>'بازپرداخت با مرجع '.$data['refund_ref_id'].' ثبت شد.',
   ]);
   return null;
  });
  if($error)return back()->withErrors(['refund'=>$error]);
  return back()->with('success','بازپرداخت انجام‌شده در درگاه با شماره مرجع ثبت شد.');
 }
 public function updateOrderStatus(Request $request,Order $order,SmsService $sms,StockReservationService $reservations) {
  $data=$request->validate(['status'=>['required','in:pending,processing,shipped,delivered,cancelled'],'shipping_carrier'=>['nullable','string','max:100'],'tracking_code'=>['nullable','string','max:120']]);
  $error=DB::transaction(function()use($order,$data,$reservations){
   $locked=Order::whereKey($order->id)->lockForUpdate()->first();
   if(in_array($locked->status,['cancelled','refund_required'],true)&&$data['status']!==$locked->status)return 'سفارش لغوشده یا نیازمند بازپرداخت قابل فعال‌سازی مجدد نیست.';
   if($locked->status==='paid_stock_issue'&&!$locked->stock_deducted_at)return 'ابتدا مشکل موجودی را از طریق گزینه تلاش مجدد برای کسر موجودی برطرف کنید.';
   if($locked->payment_status==='paid'&&$data['status']==='cancelled')return 'سفارش پرداخت‌شده را نمی‌توان بدون ثبت بازپرداخت لغو کرد.';
   if(in_array($data['status'],['processing','shipped','delivered'],true)&&($locked->payment_status!=='paid'||!$locked->stock_deducted_at))return 'تا پیش از تأیید پرداخت و کسر موجودی، امکان تغییر این وضعیت وجود ندارد.';
   $allowedTransitions=[
    'pending'=>['pending','processing','cancelled'],
    'processing'=>$locked->shipping_method==='pickup'?['processing','delivered']:['processing','shipped'],
    'shipped'=>['shipped','delivered'],
    'delivered'=>['delivered'],
   ];
   if(!in_array($data['status'],$allowedTransitions[$locked->status]??[],true))return 'برای ثبت این وضعیت، ابتدا باید مرحله قبلی سفارش تکمیل شود.';
   $previousStatus=$locked->status;
   $changes=['status'=>$data['status']];if($data['status']==='shipped')$changes['shipped_at']=$locked->shipped_at??now();if($data['status']==='delivered')$changes['delivered_at']=$locked->delivered_at??now();if(!empty($data['shipping_carrier']))$changes['shipping_carrier']=$data['shipping_carrier'];if(!empty($data['tracking_code']))$changes['tracking_code']=$data['tracking_code'];if($data['status']==='cancelled'&&$locked->payment_status!=='paid')$reservations->releaseForOrder($locked);$locked->update($changes);
   if($previousStatus!==$data['status']){
    OrderStatusHistory::create([
     'order_id'=>$locked->id,'admin_user_id'=>auth()->id(),
     'from_status'=>$previousStatus,'to_status'=>$data['status'],
     'actor_type'=>'admin','note'=>'وضعیت سفارش از پنل مدیریت تغییر کرد.',
    ]);
   }
   return null;
  });
  if($error)return back()->withErrors(['status'=>$error]);
  if($data['status']==='shipped')$sms->sendShipmentUpdateOnce($order->fresh());
  if($data['status']==='delivered')$sms->sendDeliveryUpdateOnce($order->fresh());
  return back()->with('success','وضعیت سفارش به‌روزرسانی شد.');
 }
 public function coupons(): View { return view('admin.coupons.index',['coupons'=>\App\Models\Coupon::latest()->paginate(30)]); }
 public function storeCoupon(Request $request) {
  $data=$request->validate(['code'=>['required','string','max:40','regex:/^[A-Za-z0-9_-]+$/','unique:coupons,code'],'type'=>['required','in:fixed,percent'],'value'=>['required','integer','min:1'],'minimum_order_toman'=>['required','integer','min:0','max:1000000000'],'maximum_discount_toman'=>['nullable','integer','min:1','max:1000000000'],'usage_limit'=>['nullable','integer','min:1','max:1000000'],'starts_at'=>['nullable','date'],'expires_at'=>['nullable','date','after_or_equal:starts_at'],'is_active'=>['nullable','boolean']]);
  if($data['type']==='percent'&&$data['value']>100)throw ValidationException::withMessages(['value'=>'درصد تخفیف نمی‌تواند بیشتر از ۱۰۰ باشد.']);
  $data['code']=strtoupper($data['code']);$data['is_active']=$request->boolean('is_active');\App\Models\Coupon::create($data);
  return back()->with('success','کد تخفیف ساخته شد.');
 }
 public function toggleCoupon(\App\Models\Coupon $coupon) { $coupon->update(['is_active'=>!$coupon->is_active]);return back()->with('success','وضعیت کد تخفیف تغییر کرد.'); }
 private function validatedProduct(Request $request,?Product $product=null): array {
  return $request->validate([
   'category_id'=>['nullable','exists:categories,id'],'name'=>['required','string','max:190'],
   'slug'=>['nullable','string','max:190','regex:/^[a-z0-9-]+$/',Rule::unique('products','slug')->ignore($product?->id)],
   'sku'=>['nullable','string','max:100',Rule::unique('products','sku')->ignore($product?->id)],
   'short_description'=>['nullable','string','max:500'],'description'=>['nullable','string'],
   'price'=>['required','integer','min:0'],'compare_price'=>['nullable','integer','min:0','gt:price'],
   'stock'=>['required','integer','min:0'],'image'=>['nullable','image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'gallery_images'=>['nullable','array','max:8'],'gallery_images.*'=>['image','mimes:jpg,jpeg,png,webp,avif','max:5120'],'remove_gallery'=>['nullable','array'],'remove_gallery.*'=>['integer','min:0'],'remove_image'=>['nullable','boolean'],'remove_model_3d'=>['nullable','boolean'],'model_3d'=>['nullable','file','max:20480',function($attribute,$file,$fail){
    $path=$file->getRealPath();$handle=$path?@fopen($path,'rb'):false;
    if(!$handle){$fail('فایل مدل سه‌بعدی قابل خواندن نیست.');return;}
    $header=fread($handle,20);fclose($handle);
    if(strlen($header)!==20||substr($header,0,4)!=='glTF'){$fail('فایل باید یک مدل معتبر GLB نسخه ۲ باشد.');return;}
    $version=unpack('V',substr($header,4,4))[1];$length=unpack('V',substr($header,8,4))[1];$chunkLength=unpack('V',substr($header,12,4))[1];
    if($version!==2||$length!==$file->getSize()||substr($header,16,4)!=='JSON'||$chunkLength<2||$chunkLength%4!==0||20+$chunkLength>$file->getSize()){$fail('ساختار فایل GLB معتبر نیست.');return;}
    $handle=fopen($path,'rb');fseek($handle,20);$json=fread($handle,$chunkLength);$document=json_decode($json,true);
    if(!is_array($document)||($document['asset']['version']??null)!=='2.0'){$fail('مدل سه‌بعدی باید با استاندارد glTF 2.0 سازگار باشد.');fclose($handle);return;}
    if((isset($document['buffers'])&&!is_array($document['buffers']))||(isset($document['images'])&&!is_array($document['images']))){$fail('ساختار منابع مدل سه‌بعدی معتبر نیست.');fclose($handle);return;}
    foreach(array_merge($document['buffers']??[],$document['images']??[]) as $resource){
     if(!is_array($resource)||(isset($resource['uri'])&&!str_starts_with($resource['uri'],'data:'))){$fail('مدل سه‌بعدی نباید به فایل‌های خارجی وابسته باشد.');fclose($handle);return;}
    }
    $hasBinaryChunk=false;$binaryLength=0;
    if(20+$chunkLength<$file->getSize()){
     fseek($handle,20+$chunkLength);$chunkHeader=fread($handle,8);
     if(strlen($chunkHeader)!==8||substr($chunkHeader,4,4)!=="BIN\0"){$fail('بخش داده مدل GLB معتبر نیست.');fclose($handle);return;}
     $binaryLength=unpack('V',substr($chunkHeader,0,4))[1];
     if($binaryLength%4!==0||20+$chunkLength+8+$binaryLength!==$file->getSize()){$fail('طول بخش داده مدل GLB معتبر نیست.');fclose($handle);return;}
     $hasBinaryChunk=true;
    }
    fclose($handle);
    $buffer=$document['buffers'][0]??null;
    if($buffer&&empty($buffer['uri'])&&(!isset($buffer['byteLength'])||!$hasBinaryChunk||(int)$buffer['byteLength']>$binaryLength||$binaryLength-(int)$buffer['byteLength']>3))$fail('داده هندسی مدل GLB ناقص است.');
    if(!$buffer&&$hasBinaryChunk)$fail('ساختار بخش داده مدل GLB با فایل سازگار نیست.');
   }],
   'is_active'=>['nullable','boolean'],'is_featured'=>['nullable','boolean'],
   'seo_title'=>['nullable','string','max:190'],'seo_description'=>['nullable','string','max:300'],'canonical_url'=>['nullable','url','max:500']
  ])+['is_active'=>$request->boolean('is_active'),'is_featured'=>$request->boolean('is_featured')];
 }
}