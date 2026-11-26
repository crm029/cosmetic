<?php
use App\\Http\\Controllers\\HomeController;
use App\\Http\\Controllers\\ProductController;
use App\\Http\\Controllers\\CartController;
use App\\Http\\Controllers\\CheckoutController;
use App\\Http\\Controllers\\PaymentController;
use App\\Http\\Controllers\\AdminAuthController;
use App\\Http\\Controllers\\AdminController;
use Illuminate\\Support\\Facades\\Route;
Route::get('/',[HomeController::class,'index'])->name('home');
Route::get('/shop',[ProductController::class,'index'])->name('shop');
Route::get('/product/{product:slug}',[ProductController::class,'show'])->name('products.show');
Route::get('/cart',[CartController::class,'index'])->name('cart.index');
Route::post('/cart/{product}',[CartController::class,'add'])->name('cart.add');
Route::patch('/cart/{product}',[CartController::class,'update'])->name('cart.update');
Route::delete('/cart/{product}',[CartController::class,'remove'])->name('cart.remove');
Route::get('/checkout',[CheckoutController::class,'index'])->name('checkout.index');
Route::post('/checkout',[CheckoutController::class,'store'])->name('checkout.store');
Route::get('/payment/callback',[PaymentController::class,'callback'])->name('payment.callback');
Route::get('/admin/login',[AdminAuthController::class,'show'])->name('admin.login');
Route::post('/admin/login',[AdminAuthController::class,'login'])->middleware('throttle:5,1')->name('admin.login.submit');
Route::middleware(['auth','admin'])->prefix('admin')->name('admin.')->group(function(){
 Route::get('/',[AdminController::class,'dashboard'])->name('dashboard');
 Route::post('/logout',[AdminAuthController::class,'logout'])->name('logout');
 Route::get('/products',[AdminController::class,'products'])->name('products');
 Route::get('/products/create',[AdminController::class,'createProduct'])->name('products.create');
 Route::post('/products',[AdminController::class,'storeProduct'])->name('products.store');
 Route::get('/products/{product}/edit',[AdminController::class,'editProduct'])->name('products.edit');
 Route::put('/products/{product}',[AdminController::class,'updateProduct'])->name('products.update');
 Route::delete('/products/{product}',[AdminController::class,'deleteProduct'])->name('products.delete');
});
Route::get('/robots.txt',fn()=>response("User-agent: *\\nAllow: /\\nDisallow: /account\\nDisallow: /cart\\nDisallow: /checkout\\nDisallow: /admin\\nSitemap: ".url('/sitemap.xml'),200)->header('Content-Type','text/plain'));
Route::get('/sitemap.xml',function(){
 $urls=[['loc'=>route('home'),'priority'=>'1.0'],['loc'=>route('shop'),'priority'=>'0.9']];
 if(\\Illuminate\\Support\\Facades\\Schema::hasTable('products'))foreach(\\App\\Models\\Product::where('is_active',true)->get() as $p)$urls[]=['loc'=>route('products.show',$p),'priority'=>'0.8'];
 return response()->view('seo.sitemap',compact('urls'))->header('Content-Type','application/xml');
})->name('sitemap');