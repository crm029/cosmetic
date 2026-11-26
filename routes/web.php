<?php
use App\\Http\\Controllers\\HomeController;
use App\\Http\\Controllers\\ProductController;
use Illuminate\\Support\\Facades\\Route;
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/shop', [ProductController::class, 'index'])->name('shop');
Route::get('/product/{product:slug}', [ProductController::class, 'show'])->name('products.show');
Route::get('/robots.txt', fn () => response("User-agent: *\\nAllow: /\\nDisallow: /account\\nDisallow: /cart\\nSitemap: ".url('/sitemap.xml'),200)->header('Content-Type','text/plain'));
Route::get('/sitemap.xml', function () {
 $urls=[['loc'=>route('home'),'priority'=>'1.0'],['loc'=>route('shop'),'priority'=>'0.9']];
 if(\\Illuminate\\Support\\Facades\\Schema::hasTable('products')) foreach(\\App\\Models\\Product::where('is_active',true)->get() as $p) $urls[]=['loc'=>route('products.show',$p),'priority'=>'0.8'];
 return response()->view('seo.sitemap',compact('urls'))->header('Content-Type','application/xml');
})->name('sitemap');