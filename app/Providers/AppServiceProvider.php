<?php
namespace App\Providers;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\URL;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
 public function register(): void {}
 public function boot(): void {
  if($this->app->environment('production')) {
   URL::forceScheme('https');
   config(['session.secure'=>true,'session.http_only'=>true,'session.same_site'=>'lax']);
  }
  RateLimiter::for('admin-login',fn(Request $request)=>[
   Limit::perMinute(10)->by('admin-ip|'.$request->ip()),
   Limit::perMinute(5)->by('admin-mobile|'.preg_replace('/\\D+/','',(string)$request->input('mobile'))),
  ]);
  RateLimiter::for('customer-otp-send',fn(Request $request)=>[
   Limit::perMinutes(10,10)->by('otp-send-ip|'.$request->ip()),
   Limit::perMinutes(10,3)->by('otp-send-mobile|'.preg_replace('/\\D+/','',(string)$request->input('mobile'))),
  ]);
  RateLimiter::for('customer-otp-verify',fn(Request $request)=>[
   Limit::perMinutes(10,20)->by('otp-verify-ip|'.$request->ip()),
   Limit::perMinutes(10,5)->by('otp-verify-mobile|'.preg_replace('/\\D+/','',(string)$request->input('mobile'))),
  ]);
  RateLimiter::for('order-track',fn(Request $request)=>[
   Limit::perMinute(20)->by('order-track-ip|'.$request->ip()),
   Limit::perMinute(5)->by('order-track-mobile|'.preg_replace('/\\D+/','',(string)$request->input('mobile'))),
  ]);
  RateLimiter::for('admin-mfa',fn(Request $request)=>[
   Limit::perMinute(5)->by('admin-mfa-user|'.($request->user()?->id??'guest')),
   Limit::perMinute(20)->by('admin-mfa-ip|'.$request->ip()),
  ]);
  RateLimiter::for('order-payment-retry',fn(Request $request)=>[
   Limit::perMinutes(10,10)->by('order-pay-ip|'.$request->ip()),
   Limit::perMinutes(10,3)->by('order-pay-mobile|'.preg_replace('/\\D+/','',(string)$request->input('mobile'))),
  ]);
  View::composer('layouts.app',function($view){
   $data=$view->getData();$product=$data['product']??null;$category=$data['category']??null;$storeSettings=StoreSetting::current();
   $productImage=data_get($product,'image');$categoryImage=data_get($category,'image');
   $socialImage=$productImage?asset('storage/'.$productImage):($categoryImage?asset('storage/'.$categoryImage):($storeSettings->logo_path?asset('storage/'.$storeSettings->logo_path):null));
   $catalogFiltered=request()->filled('q')||request()->filled('category')||request()->filled('min_price')||request()->filled('max_price')||request()->boolean('in_stock')||(request()->filled('sort')&&request('sort')!=='latest');
   $noIndex=request()->routeIs('cart.*','checkout.*','admin.*','orders.track*','account.*','customer.login')||(request()->routeIs('shop')&&$catalogFiltered);
   $canonicalUrl=request()->url();
   if($product && filled($product->canonical_url)){
    $candidate=$product->canonical_url;
    $parts=parse_url($candidate);
    if(is_array($parts)&&in_array(strtolower($parts['scheme']??''),['http','https'],true)&&!empty($parts['host'])&&strcasecmp($parts['host'],request()->getHost())===0){
     $canonicalUrl=$candidate;
    }
   } elseif(request()->routeIs('shop','categories.show')&&request()->integer('page',1)>1){
    $canonicalUrl=request()->fullUrlWithQuery(['page'=>request()->integer('page')]);
   }
   $view->with(['storeSettings'=>$storeSettings,'socialImage'=>$socialImage,'noIndex'=>$noIndex,'canonicalUrl'=>$canonicalUrl]);
  });
 }
}