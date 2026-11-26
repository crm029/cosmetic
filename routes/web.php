<?php
use App\\Http\\Controllers\\HomeController;
use App\\Http\\Controllers\\ProductController;
use App\\Http\\Controllers\\CartController;
use App\\Http\\Controllers\\CheckoutController;
use App\\Http\\Controllers\\PaymentController;
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
Route::get('/robots.txt',fn()=>response("User-agent: *\\nAllow: /\\nDisallow: /account\\nDisallow: /cart\\nDisallow: /checkout\\nSitemap: ".url('/sitemap.xml'),200)->header('Content-Type','text/plain'));
Route::get('/sitemap.xml',function(){
 $urls=[['loc'=>route('home'),'priority'=>'1.0'],['loc'=>route('shop'),'priority'=>'0.9']];
 if(\\Illuminate\\Support\\Facades\\Schema::hasTable('products'))foreach(\\App\\Models\\Product::where('is_active',true)->get() as $p)$urls[]=['loc'=>route('products.show',$p),'priority'=>'0.8'];
 return response()->view('seo.sitemap',compact('urls'))->header('Content-Type','application/xml');
})->name('sitemap');